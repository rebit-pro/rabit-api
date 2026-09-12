<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;
use Ramsey\Uuid\Uuid;

final readonly class SaveInstitutionUseCase
{
    public function __construct(
        private InstitutionRepository $institutions,
        private InstitutionOperationRepository $operations,
        private InstitutionAccessInterface $access,
        private InstitutionTransactionInterface $transaction,
    ) {}

    public function execute(int $actor, string $bearer, ?InstitutionId $id, InstitutionMutationInputDto $input): InstitutionMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actor, $bearer, $id, $input): InstitutionMutationOutputDto {
            $this->access->lockState();
            // A privilege check before looking up a foreign resource; all mutations hold AccessState.
            if ('organizer' !== $this->access->scope($actor)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $row = null;
            $old = new InstitutionAssignmentOutputDto();
            if (null !== $id) {
                $row = $this->operations->lockInstitution($id)->fetch();
                if (false === $row) {
                    throw new HttpException('NOT_FOUND', 404);
                }
                $old = $this->access->assignments([(int)$row['ID']])[(int)$row['ID']] ?? $old;
            }
            $desired = new InstitutionAssignmentOutputDto(
                curatorId: $input->curatorProvided ? $input->curatorId : $old->curatorId,
                headId: $input->headProvided ? $input->headId : $old->headId,
            );
            $users = [];
            foreach ([$old->curatorId, $old->headId, $desired->curatorId, $desired->headId] as $user) {
                if (null !== $user) {
                    $users[] = $user;
                }
            }
            $this->access->lockParticipants($actor, $bearer, $users);
            $operation = null === $id ? 'POST /institutions' : 'PATCH /institutions/' . $id->value;
            $hash = $input->payloadHash();
            $previous = $this->operations->find($actor, $operation, $input->key)->fetch();
            if (false !== $previous) {
                if (!hash_equals((string)$previous['payload_hash'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }
                /** @var array{
                 *     id: string,
                 *     revision: int,
                 *     assignmentSignature: string,
                 * } $result */
                $result = json_decode((string)$previous['result_json'], true, 512, JSON_THROW_ON_ERROR);

                return new InstitutionMutationOutputDto($result['id'], $result['revision'], $result['assignmentSignature']);
            }
            $details = new InstitutionDetails($input->name ?? (string)($row['UF_NAME'] ?? ''), $input->address ?? (string)($row['UF_ADDRESS'] ?? ''));
            $entityId = $id ?? new InstitutionId(Uuid::uuid4()->toString());
            if (null === $id) {
                $this->institutions->create($entityId, $details);
                $revision = 1;
                $row = $this->operations->lockInstitution($entityId)->fetch();
            } else {
                $revision = $this->institutions->update($id, $details, $input->revision ?? 0);
            }
            if (false === $row) {
                throw new \RuntimeException('Institution persistence failed.');
            }
            $operationId = Uuid::uuid4()->toString();
            $signature = $this->access->replace(
                institutionId: (int)$row['ID'],
                desired: $desired,
                expectedSignature: $input->assignmentSignature,
                replaceOccupied: $input->replaceAssignments,
                actorUserId: $actor,
                operationId: $operationId,
            );
            $this->operations->record(
                id: (int)$row['ID'],
                from: $revision - 1,
                to: $revision,
                actor: $actor,
                operationId: $operationId,
                delta: json_encode([
                    'before' => null === $id ? null : ['name' => $row['UF_NAME'], 'address' => $row['UF_ADDRESS']],
                    'after' => ['name' => $details->name, 'address' => $details->address],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            $result = new InstitutionMutationOutputDto($entityId->value, $revision, $signature);
            $this->operations->save($actor, $operation, $input->key, $hash, json_encode($result, JSON_THROW_ON_ERROR));

            return $result;
        });
    }
}
