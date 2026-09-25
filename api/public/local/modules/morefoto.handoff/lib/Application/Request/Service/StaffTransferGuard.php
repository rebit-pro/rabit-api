<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Service;

use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Допускает к превью и подтверждению льготного переноса только организатора и куратора учреждения заявки
 * и только заявку в состоянии submitted. Отказы Access проходят с их кодами UNAUTHORIZED/FORBIDDEN.
 */
final readonly class StaffTransferGuard
{
    public function __construct(private GroupLinkAccessInterface $access) {}

    public function actor(int $actorId): LinkActorOutputDto
    {
        return $this->reviewer(fn(): LinkActorOutputDto => $this->access->actor($actorId));
    }

    /** После lockState() и блокировки групп Organization. */
    public function lockedActor(int $actorId): LinkActorOutputDto
    {
        return $this->reviewer(fn(): LinkActorOutputDto => $this->access->lockActor($actorId));
    }

    /** @param array<string, mixed> $request */
    public function assertVisible(LinkActorOutputDto $actor, array $request): void
    {
        $visible = match ($actor->role) {
            'organizer' => true,
            'curator' => in_array((int)$request['INSTITUTION_ID'], $actor->institutionIds, true),
            default => false,
        };
        if (!$visible) {
            throw new HttpException('STAFF_REQUEST_NOT_FOUND', 404);
        }
    }

    /** @param array<string, mixed> $request */
    public function assertSubmitted(array $request): void
    {
        $status = (string)$request['STATUS'];
        if ('transferred' === $status) {
            throw new HttpException('REQUEST_TRANSFERRED', 409);
        }
        if ('submitted' !== $status) {
            throw new HttpException('REQUEST_NOT_SUBMITTED', 409);
        }
    }

    /** @param callable(): LinkActorOutputDto $read */
    private function reviewer(callable $read): LinkActorOutputDto
    {
        $actor = $read();
        if (!in_array($actor->role, ['organizer', 'curator'], true)) {
            throw new HttpException('FORBIDDEN', 403);
        }

        return $actor;
    }
}
