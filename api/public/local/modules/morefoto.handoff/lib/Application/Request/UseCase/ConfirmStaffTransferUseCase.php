<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferOutputDto;
use Morefoto\Handoff\Application\Request\Service\StaffTransferGuard;
use Morefoto\Handoff\Application\Request\Service\StaffTransferPlanner;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Подтверждает льготный перенос: в одной транзакции переносит полные наборы детей заявки в staff-группу через Media,
 * записывает итог по строкам и статус transferred. Заказы не меняются, подготовка ссылок затронутых групп сбрасывается
 * их подписью; повтор после переноса возвращает сохранённый итог без второго переноса.
 */
final readonly class ConfirmStaffTransferUseCase
{
    private const string DEFAULT_COMMENT = 'Полные наборы перенесены в папку сотрудников. История заказов сохранена.';

    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkAccessInterface $access,
        private GroupCalendarInterface $calendar,
        private StaffTransferGuard $guard,
        private StaffRequestRepository $requests,
        private StaffTransferPlanner $planner,
        private ChildTransferInterface $children,
    ) {}

    public function execute(int $actorId, string $requestId, IdempotencyKey $key, StaffTransferInputDto $input): StaffTransferOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $requestId, $key, $input): StaffTransferOutputDto {
            $request = $this->requests->request($requestId) ?? throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
            // Lock order shared with link commands: Access state → Organization groups → actor → Handoff → Media → Commerce.
            $this->access->lockState();
            $locked = $this->lockGroups($request);
            $actor = $this->guard->lockedActor($actorId);
            $this->guard->assertVisible($actor, $request);
            $resource = '/staff-requests/' . $requestId . '/transfers';
            $hash = hash('sha256', json_encode([
                'revision' => $input->revision,
                'signature' => $input->signature,
                'reason' => $input->reason,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $stored = $this->requests->idempotency($actor->id, $resource, $key->value);
            if (null !== $stored) {
                if (!hash_equals($stored['PAYLOAD_HASH'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return $this->restore($stored['RESULT_JSON'], (int)$request['ID']);
            }
            $request = $this->requests->request($requestId, true) ?? throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
            // A transferred request is confirmed again only as a replay of its stored results.
            $revision = 'transferred' === $request['STATUS'] ? (int)$request['REVISION'] : $this->transfer($request, $locked, $actor, $input);
            $output = new StaffTransferOutputDto($requestId, $revision, 'transferred', $this->requests->results((int)$request['ID']));
            // Results live in the request rows and never change after the transfer; the receipt stays small.
            $this->requests->saveIdempotency($actor->id, $resource, $key->value, $hash, json_encode([
                'id' => $output->id,
                'revision' => $output->revision,
                'status' => $output->status,
            ], JSON_THROW_ON_ERROR));

            return $output;
        });
    }

    /**
     * @param array<string, mixed> $request
     * @param list<string>         $locked
     */
    private function transfer(array $request, array $locked, LinkActorOutputDto $actor, StaffTransferInputDto $input): int
    {
        $this->guard->assertSubmitted($request);
        if ($input->revision !== (int)$request['REVISION']) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
        $planned = $this->planner->plan($request, true);
        $groups = array_column($planned->rows, 'groupPublicId');
        $groups[] = $planned->targetGroupPublicId;
        // Rows or staff groups changed after the unlocked read: only a fresh preview may be confirmed.
        if ([] !== array_diff($groups, $locked) || !hash_equals($planned->plan->signature, $input->signature)) {
            throw new HttpException('SIGNATURE_CONFLICT', 409);
        }
        $moves = [];
        foreach ($planned->plan->bundles as $index => $bundle) {
            $moves[] = new ChildMoveInputDto($planned->rows[$index]['childId'], $planned->targetGroupId, $bundle['targetCode']);
        }
        $this->children->move((int)$request['SHOOT_ID'], $moves);
        foreach ($planned->plan->bundles as $index => $bundle) {
            $this->requests->recordTransfer(
                $planned->rows[$index]['id'],
                $bundle['childCode'],
                $planned->targetGroupId,
                $bundle['targetCode'],
                array_column($bundle['photos'], 'id'),
            );
        }
        $revision = $this->requests->markTransferred((int)$request['ID'], (int)$request['REVISION']);
        $this->requests->appendHistory((int)$request['ID'], 'transferred', $actor->id, $actor->name, '' === $input->reason ? self::DEFAULT_COMMENT : $input->reason, true);

        return $revision;
    }

    /**
     * Блокирует исходные группы строк и staff-группы съёмки в едином порядке, чтобы переносы не встречались с командами ссылок.
     *
     * @param array<string, mixed> $request
     *
     * @return list<string>
     */
    private function lockGroups(array $request): array
    {
        $groups = array_column($this->requests->transferRows((int)$request['ID']), 'groupPublicId');
        foreach ($this->planner->staffGroups((string)$request['SHOOT_PUBLIC_ID']) as $group) {
            $groups[] = $group->publicId;
        }
        $groups = array_values(array_unique($groups));
        sort($groups);
        foreach ($groups as $groupId) {
            $this->calendar->lock($groupId);
        }

        return $groups;
    }

    private function restore(string $json, int $requestId): StaffTransferOutputDto
    {
        $value = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Invalid stored staff transfer result.');
        }

        return new StaffTransferOutputDto((string)$value['id'], (int)$value['revision'], (string)$value['status'], $this->requests->results($requestId));
    }
}
