<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Service;

use Morefoto\Handoff\Application\Link\Dto\LinkCommandOutputDto;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Открывает команду над ссылкой группы в общем порядке блокировок Access → Organization → актор → Handoff → Media/Commerce.
 * Проверяет область и право действия, возвращает сохранённый результат повтора и подтверждает актуальность формы.
 */
final readonly class GroupLinkCommandSession
{
    public function __construct(
        private GroupLinkAccessInterface $access,
        private GroupCalendarInterface $calendar,
        private GroupDirectoryInterface $directory,
        private GroupLinkRepositoryInterface $links,
        private GroupLinkReadiness $readiness,
        private LinkPermissionPolicy $permissions,
    ) {}

    /**
     * Must run inside the Handoff transaction. The replay is checked after the group lock,
     * so a concurrent request with the same key waits and then receives the stored result.
     *
     * @param array<string, bool|int|string> $payload normalized request body
     */
    public function open(int $actorId, string $groupId, LinkActionEnum $action, IdempotencyKey $key, array $payload): LinkCommandOutputDto
    {
        if (null === $this->directory->find($groupId)) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }
        $this->access->lockState();
        $this->calendar->lock($groupId);
        $actor = $this->access->lockActor($actorId);
        $group = $this->directory->find($groupId) ?? throw new HttpException('GROUP_NOT_FOUND', 404);
        if (!$this->permissions->visible($actor->role, $actor->institutionIds, $actor->groupIds, $group->institutionNativeId, $group->nativeId)) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }
        if (!$this->permissions->allows($actor->role, $action)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $resource = '/groups/' . $groupId . '/' . match ($action) {
            LinkActionEnum::PREPARE => 'link-preparations',
            LinkActionEnum::TRANSMIT => 'link-transmissions',
            LinkActionEnum::CORRECT => 'link-date-corrections',
            LinkActionEnum::READ => throw new \InvalidArgumentException('Reading is not a command.'),
        };
        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return new LinkCommandOutputDto(
            actor: $actor,
            group: $group,
            state: $this->links->lock($group->nativeId),
            assessment: $this->readiness->assessLocked($group),
            resource: $resource,
            payloadHash: $payloadHash,
            replay: $this->replay($actorId, $resource, $key, $payloadHash),
        );
    }

    public function assertCurrent(LinkCommandOutputDto $command, int $revision, string $signature): void
    {
        if ($revision !== $command->state->revision) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
        if (!hash_equals($command->assessment->readiness->signature, $signature)) {
            throw new HttpException('SIGNATURE_CONFLICT', 409);
        }
    }

    /** @param array<string, bool|int|string> $result */
    public function remember(LinkCommandOutputDto $command, IdempotencyKey $key, array $result): void
    {
        $this->links->remember($command->actor->id, $command->resource, $key->value, $command->payloadHash, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return null|array<string, bool|int|string> */
    private function replay(int $actorId, string $resource, IdempotencyKey $key, string $payloadHash): ?array
    {
        $stored = $this->links->idempotency($actorId, $resource, $key->value);
        if (null === $stored) {
            return null;
        }
        if (!hash_equals($stored['payloadHash'], $payloadHash)) {
            throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
        }
        $result = json_decode($stored['result'], true, 4, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new \UnexpectedValueException('Invalid stored link command result.');
        }

        /** @var array<string, bool|int|string> $result */
        return $result;
    }
}
