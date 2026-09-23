<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkCountersOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkListInputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkPageOutputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Показывает сотруднику ссылки и сроки групп только его серверной области, с готовностью к передаче для каждой группы
 * и счётчиками по всей области: сколько групп готовится, подготовлено, в приёме, закрыто и закрывается в ближайшие дни.
 * Ключи галерей в список не попадают: ссылку выдаёт карточка группы по отдельному запросу.
 */
final readonly class ListGroupLinksUseCase
{
    private const int CLOSING_SOON_HOURS = 72;

    public function __construct(
        private GroupLinkAccessInterface $access,
        private GroupDirectoryInterface $directory,
        private GroupLinkReadiness $readiness,
        private GroupLinkRepositoryInterface $links,
        private LinkPermissionPolicy $permissions,
        private LinkReadinessPolicy $policy,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, GroupLinkListInputDto $input): GroupLinkPageOutputDto
    {
        $actor = $this->access->actor($actorId);
        if (!$this->permissions->allows($actor->role, LinkActionEnum::READ)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        [$institutionIds, $groupIds] = $this->permissions->scope($actor->role, $actor->institutionIds, $actor->groupIds);
        $page = $this->directory->page(new GroupDirectoryQueryInputDto(
            institutionIds: $institutionIds,
            groupIds: $groupIds,
            institutionId: $input->institutionId,
            shootId: $input->shootId,
            state: $input->state,
            page: $input->page,
            pageSize: $input->pageSize,
        ));
        $assessments = $this->readiness->assess($page->items);
        $states = $this->links->states(array_keys($assessments));
        $items = [];
        foreach ($page->items as $group) {
            $state = $states[$group->nativeId] ?? new LinkState();
            $assessment = $assessments[$group->nativeId];
            $prepared = $this->policy->prepared(null !== $group->calendar->sentAt, $state, $assessment->readiness);
            $items[] = $this->mapper->summary($group, $assessment, $state->revision, $prepared);
        }

        return new GroupLinkPageOutputDto(
            items: $items,
            page: $input->page,
            pageSize: $input->pageSize,
            total: $page->total,
            totalPages: (int)ceil($page->total / $input->pageSize),
            summary: $this->counters($institutionIds, $groupIds, $input),
        );
    }

    /**
     * Counters cover the whole visible scope with the institution and shoot filters, but not the state filter:
     * the client filters by them. Two aggregates and one count, whatever the number of groups.
     *
     * @param null|list<int> $institutionIds
     * @param null|list<int> $groupIds
     */
    private function counters(?array $institutionIds, ?array $groupIds, GroupLinkListInputDto $input): GroupLinkCountersOutputDto
    {
        $query = static fn(?string $state): GroupDirectoryQueryInputDto => new GroupDirectoryQueryInputDto(
            institutionIds: $institutionIds,
            groupIds: $groupIds,
            institutionId: $input->institutionId,
            shootId: $input->shootId,
            state: $state,
            page: 1,
            pageSize: 1,
        );
        $summary = $this->directory->summary($query(null), self::CLOSING_SOON_HOURS);

        return new GroupLinkCountersOutputDto(
            referenceNow: $summary->referenceNow,
            preparing: $summary->preparing,
            open: $summary->open,
            closed: $summary->closed,
            closingSoon: $summary->closingSoon,
            prepared: 0 === $summary->preparing ? 0 : $this->links->preparedCount($this->directory->nativeIds($query('preparing'))),
        );
    }
}
