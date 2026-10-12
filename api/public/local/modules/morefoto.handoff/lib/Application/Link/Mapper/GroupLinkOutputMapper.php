<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Mapper;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkSummaryOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkAssessmentOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCalendarOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkEventOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkMarkupCorrectionOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkPreparationOutputDto;
use Morefoto\Handoff\Domain\Link\Enum\LinkProblemEnum;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

/** Stateless conversion of link facts and stored command results into Application output DTOs. */
final readonly class GroupLinkOutputMapper
{
    public function summary(
        GroupDirectoryItemOutputDto $group,
        LinkAssessmentOutputDto $assessment,
        int $revision,
        bool $prepared,
        ?string $curatorName = null,
    ): GroupLinkSummaryOutputDto {
        return new GroupLinkSummaryOutputDto(...$this->fields($group, $assessment, $revision, $prepared), curatorName: $curatorName);
    }

    /**
     * @param list<array{
     *     kind: string,
     *     actorId: int,
     *     actorName: string,
     *     at: string,
     *     sentAt: ?string,
     *     closesAt: ?string,
     *     deliveryDueAt: ?string,
     *     previousSentAt: ?string,
     *     previousClosesAt: ?string,
     *     previousDeliveryDueAt: ?string,
     *     reason: ?string,
     * }> $history UTC moments formatted as Y-m-d H:i:s
     */
    public function detail(
        GroupDirectoryItemOutputDto $group,
        LinkAssessmentOutputDto $assessment,
        int $revision,
        bool $prepared,
        ?string $galleryToken,
        ?string $galleryAlias,
        \DateTimeImmutable $now,
        array $history,
        bool $markupCorrectable = false,
    ): GroupLinkOutputDto {
        $zone = new \DateTimeZone($group->calendar->timezone);
        $events = [];
        foreach ($history as $event) {
            $events[] = new LinkEventOutputDto(
                kind: $event['kind'],
                actorId: $event['actorId'],
                actorName: $event['actorName'],
                at: (string)$this->moment($event['at'], $zone),
                sentAt: $this->moment($event['sentAt'], $zone),
                closesAt: $this->moment($event['closesAt'], $zone),
                deliveryAt: $this->moment($event['deliveryDueAt'], $zone),
                previousSentAt: $this->moment($event['previousSentAt'], $zone),
                previousClosesAt: $this->moment($event['previousClosesAt'], $zone),
                previousDeliveryAt: $this->moment($event['previousDeliveryDueAt'], $zone),
                reason: $event['reason'],
            );
        }

        return new GroupLinkOutputDto(
            ...$this->fields($group, $assessment, $revision, $prepared),
            galleryToken: $galleryToken,
            galleryAlias: $galleryAlias,
            referenceNow: $now->format(\DateTimeInterface::ATOM),
            history: $events,
            markupCorrectable: $markupCorrectable,
        );
    }

    /** @return array{revision: int, signature: string, prepared: bool} */
    public function preparationResult(int $revision, string $signature): array
    {
        return ['revision' => $revision, 'signature' => $signature, 'prepared' => true];
    }

    /** @return array{revision: int, sentAt: string, closesAt: string, digitalSalesUntil: ?string, deliveryAt: string} */
    public function calendarResult(int $revision, GroupCalendarOutputDto $dates): array
    {
        if (null === $dates->sentAt || null === $dates->closesAt || null === $dates->deliveryDueAt) {
            throw new \UnexpectedValueException('A delivered link must have a complete calendar.');
        }

        return [
            'revision' => $revision,
            'sentAt' => $dates->sentAt,
            'closesAt' => $dates->closesAt,
            'digitalSalesUntil' => $dates->digitalSalesUntil,
            'deliveryAt' => $dates->deliveryDueAt,
        ];
    }

    /** @return array{revision: int, startedAt?: string} without the moment once the correction is finished */
    public function markupCorrectionResult(int $revision, ?string $startedAt): array
    {
        return null === $startedAt ? ['revision' => $revision] : ['revision' => $revision, 'startedAt' => $startedAt];
    }

    /** @param array<string, bool|int|string> $result */
    public function markupCorrection(array $result): LinkMarkupCorrectionOutputDto
    {
        return new LinkMarkupCorrectionOutputDto((int)$result['revision'], isset($result['startedAt']) ? (string)$result['startedAt'] : null);
    }

    /** @param array<string, bool|int|string> $result */
    public function preparation(array $result): LinkPreparationOutputDto
    {
        return new LinkPreparationOutputDto((int)$result['revision'], (string)$result['signature'], (bool)$result['prepared']);
    }

    /** @param array<string, null|bool|int|string> $result */
    public function calendar(array $result): LinkCalendarOutputDto
    {
        // Replays stored before E7 have no digital deadline.
        $digital = $result['digitalSalesUntil'] ?? null;

        return new LinkCalendarOutputDto(
            (int)$result['revision'],
            (string)$result['sentAt'],
            (string)$result['closesAt'],
            null === $digital ? null : (string)$digital,
            (string)$result['deliveryAt'],
        );
    }

    /**
     * @return array{
     *     groupId: string, name: string, kind: string, institutionId: string, institutionName: string,
     *     shootId: string, shootName: string, revision: int, signature: string, prepared: bool,
     *     problems: list<string>, state: string, timezone: string, sentAt: ?string, closesAt: ?string,
     *     digitalSalesUntil: ?string, deliveryAt: ?string, photoCount: int, childCount: int, markupCorrectionAt: ?string,
     * }
     */
    private function fields(GroupDirectoryItemOutputDto $group, LinkAssessmentOutputDto $assessment, int $revision, bool $prepared): array
    {
        return [
            'groupId' => $group->id,
            'name' => $group->name,
            'kind' => $group->kind,
            'institutionId' => $group->institutionId,
            'institutionName' => $group->institutionName,
            'shootId' => $group->shootId,
            'shootName' => $group->shootName,
            'revision' => $revision,
            'signature' => $assessment->readiness->signature,
            'prepared' => $prepared,
            'problems' => array_map(static fn(LinkProblemEnum $problem): string => $problem->value, $assessment->readiness->problems),
            'state' => $group->calendar->status,
            'timezone' => $group->calendar->timezone,
            'sentAt' => $group->calendar->sentAt,
            'closesAt' => $group->calendar->closesAt,
            'digitalSalesUntil' => $group->calendar->digitalSalesUntil,
            'deliveryAt' => $group->calendar->deliveryDueAt,
            'photoCount' => $assessment->photoCount,
            'childCount' => $assessment->childCount,
            'markupCorrectionAt' => $group->markupCorrectionAt,
        ];
    }

    private function moment(?string $utc, \DateTimeZone $zone): ?string
    {
        if (null === $utc) {
            return null;
        }
        $moment = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new \DateTimeZone('UTC'));
        if (false === $moment) {
            throw new \UnexpectedValueException('Invalid stored link moment.');
        }

        return $moment->setTimezone($zone)->format(\DateTimeInterface::ATOM);
    }
}
