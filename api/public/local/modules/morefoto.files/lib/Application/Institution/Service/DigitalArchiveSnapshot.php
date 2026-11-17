<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Service;

use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Service\DigitalArchiveLayout;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchiveFile;
use Rebit\Share\Contracts\Commerce\PurchasedDigitalLinesInterface;
use Rebit\Share\Contracts\Commerce\Dto\PurchasedDigitalLineOutputDto;
use Rebit\Share\Contracts\Media\BoundedChildPhotosInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

/** Фиксирует купленные электронные кадры всех групп учреждения, раскрывая только купленные комплекты по правилу J1. */
final readonly class DigitalArchiveSnapshot
{
    public function __construct(
        private GroupDirectoryInterface $groups,
        private PurchasedDigitalLinesInterface $purchases,
        private BoundedChildPhotosInterface $children,
        private PhotoFilenamesInterface $filenames,
        private OriginalFilesInterface $originals,
        private DigitalArchiveLayout $layout,
        private DigitalArchivePolicy $policy,
    ) {}

    /** @return list<DigitalArchiveFile> */
    public function capture(int $institutionNativeId, string $institutionId, \DateTimeImmutable $at): array
    {
        $files = [];
        $bytes = 0;
        $sources = 0;
        $page = 1;
        /** @var array<string, list<PurchasedDigitalLineOutputDto>> $purchases */
        $purchases = [];
        foreach ($this->purchases->forInstitution($institutionNativeId, $at) as $line) {
            $purchases[$line->groupId][] = $line;
        }
        if ([] === $purchases) {
            return [];
        }
        do {
            $groups = $this->groups->page(new GroupDirectoryQueryInputDto(
                institutionIds: null,
                groupIds: null,
                institutionId: $institutionId,
                shootId: null,
                state: null,
                page: $page,
                pageSize: 100,
            ));
            foreach ($groups->items as $group) {
                if (!isset($purchases[$group->id])) {
                    continue;
                }
                if ($group->institutionNativeId !== $institutionNativeId || $group->institutionId !== $institutionId) {
                    throw new DigitalArchiveException('COMPOSITION_CHANGED');
                }
                /** @var array<string, array<string, array{orderId: string, orderNumber: string, lineId: string, kind: string, childCode: string}>> $candidates */
                $candidates = [];
                /** @var array<int, array<int, true>> $children */
                $children = [];
                foreach ($purchases[$group->id] as $line) {
                    if ('bundle' === $line->kind) {
                        $children[$line->shootId][$line->childId] = true;
                    }
                }
                /** @var array<int, array<int, array<string, true>>> $bundles */
                $bundles = [];
                $resolved = 0;
                foreach ($children as $shootId => $ids) {
                    foreach (array_chunk(array_keys($ids), 500) as $chunk) {
                        $remaining = DigitalArchivePolicy::MAX_SOURCES - $sources - $resolved;
                        $ready = $this->children->ready($shootId, $chunk, $remaining + 1);
                        if (count($ready) > $remaining) {
                            throw new DigitalArchiveException('DIGITAL_ARCHIVE_TOO_LARGE');
                        }
                        $resolved += count($ready);
                        foreach ($ready as $photo) {
                            $bundles[$shootId][$photo->nativeChildId][$photo->photoId] = true;
                        }
                    }
                }
                foreach ($purchases[$group->id] as $line) {
                    if ($line->groupId !== $group->id) {
                        throw new DigitalArchiveException('COMPOSITION_CHANGED');
                    }
                    $photoIds = [];
                    if ('digital' === $line->kind && null !== $line->photoId) {
                        $photoIds[] = $line->photoId;
                    } elseif ('bundle' === $line->kind) {
                        $photoIds = array_keys($bundles[$line->shootId][$line->childId] ?? []);
                    }
                    if ([] === $photoIds) {
                        throw new DigitalArchiveException('ORIGINAL_MISSING');
                    }
                    foreach ($photoIds as $photoId) {
                        if (isset($candidates[$photoId][$line->lineId])) {
                            continue;
                        }
                        $candidates[$photoId][$line->lineId] = [
                            'orderId' => $line->orderId, 'orderNumber' => $line->orderNumber,
                            'lineId' => $line->lineId, 'kind' => $line->kind, 'childCode' => $line->childCode,
                        ];
                        ++$sources;
                        $this->policy->assertFits(count($files) + count($candidates), $bytes, $sources);
                    }
                }
                ksort($candidates);
                $names = [];
                $originals = [];
                foreach (array_chunk(array_keys($candidates), 500) as $ids) {
                    foreach ($this->filenames->filenames($ids) as $id => $name) {
                        $names[$id] = $this->layout->filename($name);
                    }
                    foreach ($this->originals->originals($ids) as $id => $original) {
                        $originals[$id] = $original;
                    }
                }
                foreach ($candidates as $id => $_) {
                    if (!isset($names[$id])) {
                        throw new DigitalArchiveException('ORIGINAL_MISSING');
                    }
                }
                $nested = $this->layout->collisions($names);
                $directory = $this->layout->group($group->name, $group->id);
                foreach ($candidates as $id => $reasons) {
                    $original = $originals[$id] ?? null;
                    $bytes += null === $original ? 0 : $original->bytes;
                    $files[] = new DigitalArchiveFile(
                        groupId: $group->id,
                        groupName: $group->name,
                        photoId: $id,
                        filename: $names[$id],
                        entryName: $this->layout->entry($directory, $id, $names[$id], $nested),
                        relativePath: $original?->relativePath,
                        bytes: $original?->bytes,
                        sources: array_values($reasons),
                    );
                    $this->policy->assertFits(count($files), $bytes, $sources);
                }
                unset($purchases[$group->id]);
            }
            ++$page;
        } while (($page - 1) * 100 < $groups->total && [] !== $purchases);

        if ([] !== $purchases) {
            throw new DigitalArchiveException('COMPOSITION_CHANGED');
        }

        return $files;
    }
}
