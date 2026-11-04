<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\UseCase;

use Morefoto\Organization\Application\Calendar\Dto\GroupCalendarDetailsOutputDto;
use Morefoto\Organization\Application\Calendar\Mapper\CalendarExtensionHistoryMapper;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Даёт организатору актуальные сроки и ревизию группы для предпросмотра продления.
 * Повторно проверяет Bearer после чтения, чтобы смена сессии не раскрыла календарь.
 */
final readonly class GetGroupCalendarUseCase
{
    public function __construct(
        private GroupCalendarInterface $calendar,
        private InstitutionAccessInterface $access,
        private TokenResolverInterface $tokens,
        private GroupCalendarRepository $repository,
        private CalendarExtensionHistoryMapper $historyMapper,
    ) {}

    public function execute(int $actorId, string $groupId, string $bearer): GroupCalendarDetailsOutputDto
    {
        $scope = $this->access->scope($actorId);
        if ('organizer' !== $scope->role) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $result = $this->calendar->get($groupId);
        $history = [];
        $rows = $this->repository->extensions($groupId);
        while (false !== ($row = $rows->fetch())) {
            /** @var array{UF_ACTOR_ID: int|string, ACTOR_NAME: string, UF_OCCURRED_AT: string, UF_DELTA: string} $row */
            $history[] = $this->historyMapper->fromRow($row);
        }
        if ($actorId !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $this->access->scope($actorId)->accessRevision) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new GroupCalendarDetailsOutputDto($result->groupId, $result->revision, $result->calendar, $history);
    }
}
