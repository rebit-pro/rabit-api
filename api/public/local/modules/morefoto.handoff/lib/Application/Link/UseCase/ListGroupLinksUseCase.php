<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

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
 * Показывает сотруднику ссылки и сроки групп только его серверной области, с готовностью к передаче для каждой группы.
 * Ключи галерей в список не попадают: ссылку выдаёт карточка группы по отдельному запросу.
 */
final readonly class ListGroupLinksUseCase
{
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

        return new GroupLinkPageOutputDto($items, $input->page, $input->pageSize, $page->total, (int)ceil($page->total / $input->pageSize));
    }
}
