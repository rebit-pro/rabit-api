<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Service;

use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetPurchaseOutputDto;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Разрешает исходные имена отдельных покупок и конкретные готовые файлы купленного комплекта по правилу J1.
 * Запрашивает наборы пакетно по съёмке и детям снимка, не используя широкие права J4 или подарков как продажи.
 */
final readonly class WorksheetFiles
{
    private const int MAX_FILES = 50000;

    public function __construct(private ChildPhotosInterface $children, private PhotoFilenamesInterface $filenames) {}

    /**
     * @param list<WorksheetPurchaseOutputDto>            $purchases
     * @param array<string, list<WorksheetFileOutputDto>> $fixed     сохранённые печатные файлы, не требующие Media
     *
     * @return array<string, list<WorksheetFileOutputDto>> файлы по ID строки покупки
     */
    public function resolve(array $purchases, array $fixed = []): array
    {
        $photos = [];
        $shoots = [];
        $bundleChildren = [];
        foreach ($purchases as $purchase) {
            if (isset($fixed[$purchase->lineId])) {
                continue;
            }
            if (null !== $purchase->photoId) {
                $photos[$purchase->photoId] = $purchase->photoId;
            }
            if (in_array($purchase->kind, ['digital', 'bundle'], true)) {
                $shoots[$purchase->shootId][$purchase->childId] = $purchase->childId;
                if ('bundle' === $purchase->kind) {
                    $bundleChildren[$purchase->shootId][$purchase->childId] = true;
                }
            }
        }
        /** @var array<int, array<int, array<string, true>>> $sets */
        $sets = [];
        $codes = [];
        foreach ($shoots as $shootId => $childIds) {
            foreach ($this->children->ready($shootId, array_values($childIds)) as $assignment) {
                if (!isset($childIds[$assignment->nativeChildId])
                    || (!isset($bundleChildren[$shootId][$assignment->nativeChildId]) && !isset($photos[$assignment->photoId]))) {
                    continue;
                }
                $sets[$shootId][$assignment->nativeChildId][$assignment->photoId] = true;
                $codes[$shootId . ':' . $assignment->nativeChildId][$assignment->photoId] = $assignment;
                $photos[$assignment->photoId] = $assignment->photoId;
                if (self::MAX_FILES < count($photos)) {
                    throw new HttpException('WORKSHEET_TOO_LARGE', 422);
                }
            }
        }
        $names = [] === $photos ? [] : $this->filenames->filenames(array_values($photos));
        $result = [];
        $resolved = [];
        $bundles = [];
        $references = 0;
        foreach ($purchases as $purchase) {
            if (isset($fixed[$purchase->lineId])) {
                $references += count($fixed[$purchase->lineId]);
                if (self::MAX_FILES < $references) {
                    throw new HttpException('WORKSHEET_TOO_LARGE', 422);
                }
                $result[$purchase->lineId] = $fixed[$purchase->lineId];
                continue;
            }
            $ids = 'bundle' === $purchase->kind ? array_keys($sets[$purchase->shootId][$purchase->childId] ?? [])
                : (null === $purchase->photoId ? [] : [$purchase->photoId]);
            $references += count($ids);
            if (self::MAX_FILES < $references) {
                throw new HttpException('WORKSHEET_TOO_LARGE', 422);
            }
            $key = $purchase->shootId . ':' . $purchase->childId;
            if ('bundle' === $purchase->kind && isset($bundles[$key])) {
                $result[$purchase->lineId] = $bundles[$key];
                continue;
            }
            $files = [];
            foreach ($ids as $id) {
                $name = $names[$id] ?? null;
                $assignment = $codes[$key][$id] ?? null;
                $files[] = $resolved[$key][$id] ??= new WorksheetFileOutputDto(
                    photoId: $id,
                    filename: '' === $name ? null : $name,
                    path: null,
                    printsInFile: null,
                    code: $assignment?->code,
                    childCode: $assignment?->childCode,
                );
            }
            if ('bundle' === $purchase->kind) {
                $bundles[$key] = $files;
            }
            $result[$purchase->lineId] = $files;
        }

        return $result;
    }
}
