<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Service;

use Morefoto\Handoff\Domain\Link\Enum\LinkProblemEnum;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkFacts;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkReadiness;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;

/**
 * Решает, можно ли открыть галерею группы родителям, и подписывает проверенное состояние материалов и условий.
 * Подпись меняется при любом изменении кадров, назначений, обложки, условий, названия, воспитателя или незавершённых списков,
 * поэтому устаревшая проверка не открывает галерею.
 */
final readonly class LinkReadinessPolicy
{
    public function evaluate(LinkFacts $facts): LinkReadiness
    {
        $problems = [];
        if (0 === $facts->readyPhotos) {
            $problems[] = LinkProblemEnum::NO_PHOTOS;
        }
        if (0 < $facts->processingPhotos) {
            $problems[] = LinkProblemEnum::PHOTOS_PROCESSING;
        }
        if (0 < $facts->unassignedPhotos) {
            $problems[] = LinkProblemEnum::UNASSIGNED_PHOTOS;
        }
        if (0 === $facts->activeProducts) {
            $problems[] = LinkProblemEnum::NO_PRODUCTS;
        }
        if ([] !== $facts->pendingRequests) {
            $problems[] = LinkProblemEnum::STAFF_REQUESTS_PENDING;
        }

        return new LinkReadiness(hash('sha256', json_encode([
            'group' => [$facts->groupId, $facts->groupName, $facts->groupKind, $facts->shootId, $facts->institutionId, $facts->teacherId],
            'media' => $facts->materialsFingerprint,
            'sales' => $facts->salesFingerprint,
            'staff' => $facts->pendingRequests,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $problems);
    }

    /** After the delivery the link stays handed over; before it only a confirmation of the current state counts. */
    public function prepared(bool $sent, LinkState $state, LinkReadiness $readiness): bool
    {
        return $sent || (null !== $state->preparedSignature && hash_equals($state->preparedSignature, $readiness->signature) && [] === $readiness->problems);
    }
}
