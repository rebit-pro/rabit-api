<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkPageOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkSummaryOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCalendarOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkEventOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkPreparationOutputDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\GroupLinkCountersResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\GroupLinkListResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\GroupLinkResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\GroupLinkSummaryResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\LinkCalendarResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\LinkEventResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\LinkPreparationResultDto;

final readonly class GroupLinkResultMapper
{
    public function list(GroupLinkPageOutputDto $output): GroupLinkListResultDto
    {
        return new GroupLinkListResultDto(array_map($this->summary(...), $output->items));
    }

    /** @return array{page: int, pageSize: int, total: int, totalPages: int, summary: GroupLinkCountersResultDto} */
    public function meta(GroupLinkPageOutputDto $output): array
    {
        return [
            'page' => $output->page,
            'pageSize' => $output->pageSize,
            'total' => $output->total,
            'totalPages' => $output->totalPages,
            'summary' => new GroupLinkCountersResultDto(
                referenceNow: $output->summary->referenceNow,
                byState: ['preparing' => $output->summary->preparing, 'open' => $output->summary->open, 'closed' => $output->summary->closed],
                closingSoon: $output->summary->closingSoon,
                prepared: $output->summary->prepared,
            ),
        ];
    }

    public function detail(GroupLinkOutputDto $output): GroupLinkResultDto
    {
        return new GroupLinkResultDto(
            groupId: $output->groupId,
            name: $output->name,
            kind: $output->kind,
            institutionId: $output->institutionId,
            institutionName: $output->institutionName,
            shootId: $output->shootId,
            shootName: $output->shootName,
            revision: $output->revision,
            signature: $output->signature,
            prepared: $output->prepared,
            problems: $output->problems,
            state: $output->state,
            timezone: $output->timezone,
            sentAt: $output->sentAt,
            closesAt: $output->closesAt,
            deliveryAt: $output->deliveryAt,
            photoCount: $output->photoCount,
            childCount: $output->childCount,
            galleryToken: $output->galleryToken,
            referenceNow: $output->referenceNow,
            history: array_map($this->event(...), $output->history),
        );
    }

    public function preparation(LinkPreparationOutputDto $output): LinkPreparationResultDto
    {
        return new LinkPreparationResultDto($output->revision, $output->signature, $output->prepared);
    }

    public function calendar(LinkCalendarOutputDto $output): LinkCalendarResultDto
    {
        return new LinkCalendarResultDto($output->revision, $output->sentAt, $output->closesAt, $output->deliveryAt);
    }

    private function summary(GroupLinkSummaryOutputDto $output): GroupLinkSummaryResultDto
    {
        return new GroupLinkSummaryResultDto(
            groupId: $output->groupId,
            name: $output->name,
            kind: $output->kind,
            institutionId: $output->institutionId,
            institutionName: $output->institutionName,
            shootId: $output->shootId,
            shootName: $output->shootName,
            revision: $output->revision,
            signature: $output->signature,
            prepared: $output->prepared,
            problems: $output->problems,
            state: $output->state,
            timezone: $output->timezone,
            sentAt: $output->sentAt,
            closesAt: $output->closesAt,
            deliveryAt: $output->deliveryAt,
            photoCount: $output->photoCount,
            childCount: $output->childCount,
            curatorName: $output->curatorName,
        );
    }

    private function event(LinkEventOutputDto $output): LinkEventResultDto
    {
        return new LinkEventResultDto(
            kind: $output->kind,
            actorId: $output->actorId,
            actorName: $output->actorName,
            at: $output->at,
            sentAt: $output->sentAt,
            closesAt: $output->closesAt,
            deliveryAt: $output->deliveryAt,
            previousSentAt: $output->previousSentAt,
            previousClosesAt: $output->previousClosesAt,
            previousDeliveryAt: $output->previousDeliveryAt,
            reason: $output->reason,
        );
    }
}
