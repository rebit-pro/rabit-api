<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Request\Service;

use Morefoto\Handoff\Domain\Request\ValueObject\StaffTransferPlan;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Правила льготного переноса заявки сотрудника в папку сотрудников.
 * Требует весь текущий набор каждого ребёнка в исходной группе, запрещает закрытую цель и кадры, общие с детьми вне заявки,
 * назначает целевые коды и считает подпись проверяемого состояния, общую для превью и подтверждения.
 */
final readonly class StaffTransferPolicy
{
    /**
     * @param list<array{
     *     id: int,
     *     publicId: string,
     *     groupId: int,
     *     groupPublicId: string,
     *     childId: int,
     *     photoIds: list<string>,
     * }> $rows
     * @param array<int, ChildSetOutputDto> $sets        наборы детей по ID
     * @param list<string>                  $targetCodes свободные коды цели, не меньше числа строк
     * @param array<int, true>              $ordered     дети с заказами
     */
    public function plan(
        string $requestId,
        int $revision,
        string $targetGroupId,
        string $targetStatus,
        array $rows,
        array $sets,
        array $targetCodes,
        array $ordered,
    ): StaffTransferPlan {
        if ('closed' === $targetStatus) {
            throw new HttpException('TARGET_GROUP_CLOSED', 409);
        }
        if ([] === $rows || count($targetCodes) < count($rows)) {
            throw new HttpException('SET_CHANGED', 409);
        }
        $bundles = [];
        $signed = [];
        $shared = [];
        $hasOrders = false;
        foreach ($rows as $index => $row) {
            $set = $sets[$row['childId']] ?? null;
            if (null === $set || $set->groupId !== $row['groupId'] || [] === $set->photos) {
                throw new HttpException('SET_CHANGED', 409);
            }
            $photos = [];
            $signedPhotos = [];
            $current = [];
            foreach ($set->photos as $photo) {
                $photos[] = ['id' => $photo->id, 'code' => $photo->code, 'revision' => $photo->revision];
                $signedPhotos[] = [$photo->id, $photo->revision, $photo->status];
                $current[$photo->id] = true;
            }
            foreach ($row['photoIds'] as $photoId) {
                if (!isset($current[$photoId])) {
                    throw new HttpException('SET_CHANGED', 409);
                }
            }
            foreach ($set->sharedPhotoCodes as $code) {
                $shared[$code] = true;
            }
            $childOrdered = isset($ordered[$row['childId']]);
            $hasOrders = $hasOrders || $childOrdered;
            $bundles[] = [
                'rowId' => $row['publicId'],
                'groupId' => $row['groupPublicId'],
                'childCode' => $set->code,
                'targetCode' => $targetCodes[$index],
                'hasOrders' => $childOrdered,
                'photos' => $photos,
            ];
            $signed[] = [$row['publicId'], $set->childPublicId, $row['groupPublicId'], $set->code, $targetCodes[$index], $childOrdered, $signedPhotos];
        }
        if ([] !== $shared) {
            throw new HttpException('SHARED_PHOTO', 409, null, ['photoCodes' => array_keys($shared)]);
        }

        return new StaffTransferPlan(
            bundles: $bundles,
            hasOrders: $hasOrders,
            signature: hash('sha256', json_encode([$requestId, $revision, $targetGroupId, $targetStatus, $signed], JSON_THROW_ON_ERROR)),
        );
    }
}
