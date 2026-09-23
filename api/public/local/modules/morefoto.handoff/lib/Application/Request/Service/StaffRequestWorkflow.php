<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Service;

use Morefoto\Handoff\Application\Request\Mapper\StaffRequestOutputMapper;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Dto\ClarificationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationOutputDto;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Выполняет серверный workflow служебной заявки и сохраняет доказательство права сотрудника.
 *
 * Проверяет текущую роль, область, ребёнка, optimistic lock и идемпотентность до атомарной смены состояния и истории.
 */
final readonly class StaffRequestWorkflow
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private StaffRequestRepository $requests,
        private StaffRequestAccessInterface $access,
        private MediaScopeInterface $scopes,
        private StaffChildReferenceInterface $children,
    ) {}

    public function list(int $actorId, StaffRequestListInputDto $input): StaffRequestListOutputDto
    {
        $actor = $this->actor($actorId, ['organizer', 'curator', 'teacher']);
        $page = $this->requests->page($actor, $input->institutionId, $input->shootId, $input->status, $input->pageSize, ($input->page - 1) * $input->pageSize);
        $items = [];
        foreach ($page['items'] as $item) {
            $items[] = StaffRequestOutputMapper::fromView($item);
        }

        return new StaffRequestListOutputDto(
            items: $items,
            scope: ['role' => $actor->role, ...$this->requests->options($actor)],
            page: $input->page,
            pageSize: $input->pageSize,
            total: $page['total'],
            totalPages: (int)ceil($page['total'] / $input->pageSize),
            byStatus: $page['byStatus'],
        );
    }

    public function detail(int $actorId, string $requestId): StaffRequestOutputDto
    {
        $actor = $this->actor($actorId, ['organizer', 'curator', 'teacher']);
        $request = $this->required($requestId);
        $this->assertVisible($actor, $request);

        return StaffRequestOutputMapper::fromView($this->requests->view($request));
    }

    public function save(int $actorId, ?string $requestId, IdempotencyKey $key, StaffRequestMutationInputDto $input): StaffRequestMutationOutputDto
    {
        $actor = $this->actor($actorId, ['organizer', 'teacher']);

        return $this->transaction->execute(function() use ($actor, $requestId, $key, $input): StaffRequestMutationOutputDto {
            $resource = null === $requestId ? '/staff-requests' : '/staff-requests/' . $requestId;
            $hash = hash('sha256', json_encode([
                'requestId' => $requestId,
                'institutionId' => $input->institutionId,
                'shootId' => $input->shootId,
                'rows' => $input->rows,
                'comment' => $input->comment,
                'revision' => $input->revision,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $replayed = $this->replay($actor->id, $resource, $key, $hash);
            if (null !== $replayed) {
                return $replayed;
            }
            $previous = null;
            if (null !== $requestId) {
                $previous = $this->required($requestId, true);
                $this->assertVisible($actor, $previous);
                if ((int)$previous['CREATED_BY'] !== $actor->id) {
                    throw new HttpException('AUTHOR_REQUIRED', 403);
                }
                if ('transferred' === $previous['STATUS']) {
                    throw new HttpException('REQUEST_TRANSFERRED', 409);
                }
                if ($input->revision !== (int)$previous['REVISION']) {
                    throw new HttpException('REVISION_CONFLICT', 409);
                }
                if ($input->institutionId !== $previous['INSTITUTION_PUBLIC_ID'] || $input->shootId !== $previous['SHOOT_PUBLIC_ID']) {
                    throw new HttpException('REQUEST_SCOPE_IMMUTABLE', 422);
                }
            } elseif (null !== $input->revision) {
                throw new HttpException('INVALID_REVISION', 422);
            }
            $resolved = [];
            $children = [];
            $institutionId = null;
            $shootId = null;
            foreach ($input->rows as $row) {
                $scope = $this->scopes->resolve($input->shootId, $row['groupId']);
                if ($input->institutionId !== $scope->institutionPublicId || 'regular' !== $scope->groupKind || null === $scope->groupId) {
                    throw new HttpException('GROUP_NOT_FOUND', 404);
                }
                if ('teacher' === $actor->role && !in_array($scope->groupId, $actor->groupIds, true)) {
                    throw new HttpException('GROUP_NOT_FOUND', 404);
                }
                $child = $this->children->resolve($scope->shootId, $scope->groupId, $row['code']);
                if (isset($children[$child->nativeId])) {
                    throw new HttpException('DUPLICATE_CHILD', 422);
                }
                if ($this->requests->activeChildRequest($child->nativeId, null === $previous ? null : (int)$previous['ID'])) {
                    throw new HttpException('CHILD_ALREADY_PENDING', 409);
                }
                $children[$child->nativeId] = true;
                $institutionId ??= $scope->institutionId;
                $shootId ??= $scope->shootId;
                if ($institutionId !== $scope->institutionId || $shootId !== $scope->shootId) {
                    throw new HttpException('GROUP_NOT_FOUND', 404);
                }
                $resolved[] = [
                    'id' => $row['id'],
                    'groupId' => $scope->groupId,
                    'childId' => $child->nativeId,
                    'code' => strtoupper(trim($row['code'])),
                    'photoIds' => $child->photoIds,
                ];
            }
            if (null === $previous) {
                $publicId = Uuid::uuid4()->toString();
                $nativeId = $this->requests->create($publicId, $institutionId, $shootId, $actor, $input->comment, $resolved);
                $revision = 1;
            } else {
                $publicId = (string)$previous['PUBLIC_ID'];
                $nativeId = (int)$previous['ID'];
                $revision = $this->requests->resubmit($nativeId, (int)$previous['REVISION'], $input->comment, $resolved);
            }
            $this->requests->appendHistory($nativeId, 'submitted', $actor->id, $actor->name, $input->comment, true);
            $output = new StaffRequestMutationOutputDto($publicId, $revision, 'submitted');
            $this->remember($actor->id, $resource, $key, $hash, $output);

            return $output;
        });
    }

    public function clarify(int $actorId, string $requestId, IdempotencyKey $key, ClarificationInputDto $input): StaffRequestMutationOutputDto
    {
        $actor = $this->actor($actorId, ['organizer', 'curator']);

        return $this->transaction->execute(function() use ($actor, $requestId, $key, $input): StaffRequestMutationOutputDto {
            $resource = '/staff-requests/' . $requestId . '/clarifications';
            $hash = hash('sha256', json_encode([$requestId, $input->revision, $input->comment, $input->confirmed], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $replayed = $this->replay($actor->id, $resource, $key, $hash);
            if (null !== $replayed) {
                return $replayed;
            }
            $request = $this->required($requestId, true);
            $this->assertVisible($actor, $request);
            if (!$input->confirmed) {
                throw new HttpException('CONFIRMATION_REQUIRED', 422);
            }
            if ('transferred' === $request['STATUS']) {
                throw new HttpException('REQUEST_TRANSFERRED', 409);
            }
            if ($input->revision !== (int)$request['REVISION']) {
                throw new HttpException('REVISION_CONFLICT', 409);
            }
            $revision = $this->requests->clarify((int)$request['ID'], (int)$request['REVISION']);
            $this->requests->appendHistory((int)$request['ID'], 'clarification', $actor->id, $actor->name, $input->comment, true);
            $output = new StaffRequestMutationOutputDto($requestId, $revision, 'clarification');
            $this->remember($actor->id, $resource, $key, $hash, $output);

            return $output;
        });
    }

    /** @param list<string> $roles */
    private function actor(int $actorId, array $roles): StaffRequestActorOutputDto
    {
        $actor = $this->access->actor($actorId);
        if (!in_array($actor->role, $roles, true)) {
            throw new HttpException('FORBIDDEN', 403);
        }

        return $actor;
    }

    /** @return array<string,mixed> */
    private function required(string $id, bool $lock = false): array
    {
        $request = $this->requests->request($id, $lock);
        if (null === $request) {
            throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
        }

        return $request;
    }

    /** @param array<string,mixed> $request */
    private function assertVisible(StaffRequestActorOutputDto $actor, array $request): void
    {
        $visible = match ($actor->role) {
            'organizer' => true,
            'curator' => in_array((int)$request['INSTITUTION_ID'], $actor->institutionIds, true),
            'teacher' => (int)$request['CREATED_BY'] === $actor->id
                && [] === array_diff($this->requests->groupIds((int)$request['ID']), $actor->groupIds),
            default => false,
        };
        if (!$visible) {
            throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
        }
    }

    private function replay(int $actorId, string $resource, IdempotencyKey $key, string $hash): ?StaffRequestMutationOutputDto
    {
        $stored = $this->requests->idempotency($actorId, $resource, $key->value);
        if (null === $stored) {
            return null;
        }
        if (!hash_equals($stored['PAYLOAD_HASH'], $hash)) {
            throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
        }
        $value = json_decode($stored['RESULT_JSON'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Invalid stored handoff result.');
        }

        return new StaffRequestMutationOutputDto((string)$value['id'], (int)$value['revision'], (string)$value['status']);
    }

    private function remember(int $actorId, string $resource, IdempotencyKey $key, string $hash, StaffRequestMutationOutputDto $output): void
    {
        $this->requests->saveIdempotency($actorId, $resource, $key->value, $hash, json_encode([
            'id' => $output->id,
            'revision' => $output->revision,
            'status' => $output->status,
        ], JSON_THROW_ON_ERROR));
    }
}
