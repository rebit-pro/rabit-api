<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Service;

use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveGroup;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Morefoto\Production\Domain\Institution\ValueObject\NewInstitutionArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Фиксирует полный архив учреждения либо дополнение как ссылки на неизменяемые задания L1 и точные пути файлов.
 * Полная выгрузка повторяет прежний состав; дополнение берёт только ещё не доставленные новые версии заданий.
 * Чтение снимка восстанавливает те же строки из источников, не пересчитывая продажи и каталог.
 */
final readonly class InstitutionSnapshot
{
    public function __construct(
        private InstitutionArchiveRepositoryInterface $releases,
        private ArchiveRepositoryInterface $jobs,
        private VersionSnapshot $snapshot,
        private InstitutionGroups $groups,
        private ArchiveLayout $layout,
        private PhotoFilenamesInterface $filenames,
        private ProductionIdGeneratorInterface $ids,
        private ClockInterface $clock,
    ) {}

    public function capture(PrintInstitutionOutputDto $institution, bool $supplement = false, ?int $actorId = null, ?InstitutionArchiveRequest $request = null): ?InstitutionArchive
    {
        return $this->releases->exclusive('institution:' . $institution->id, function() use ($institution, $supplement, $actorId, $request): ?InstitutionArchive {
            try {
                $archive = $this->take($institution, $supplement, $actorId, $request);
                $this->releases->recordPlanningError($institution->nativeId, null);

                return $archive;
            } catch (\Throwable $error) {
                $code = $error->getMessage();
                $expected = in_array($code, ['PRINT_SALES_OPEN', 'BUILD_DELAY', 'NOTHING_TO_BUILD', 'ARCHIVE_NOT_READY'], true);
                $known = in_array($code, ['ORIGINAL_MISSING', 'UNSAFE_FILENAME', 'INVALID_ARCHIVE_LAYOUT', 'COMPOSITION_CHANGED'], true);
                $this->releases->recordPlanningError($institution->nativeId, $expected ? null : ($known ? $code : 'ARCHIVE_FAILED'));
                throw $error;
            }
        });
    }

    private function take(PrintInstitutionOutputDto $institution, bool $supplement, ?int $actorId, ?InstitutionArchiveRequest $request): ?InstitutionArchive
    {
        $now = $this->clock->now();
        $groups = $this->groups->all($institution->id);
        if ([] === $groups) {
            return null;
        }
        if ([] !== $this->groups->blockers($groups, $now)) {
            throw new HttpException('PRINT_SALES_OPEN', 409);
        }
        if ($this->groups->buildFrom($groups) > $now) {
            throw new HttpException('BUILD_DELAY', 409);
        }
        $history = $this->releases->history($institution->nativeId);
        if ($supplement && [] === $history) {
            throw new HttpException('ARCHIVE_NOT_READY', 409);
        }
        foreach ($groups as $group) {
            $deadline = $this->groups->deadline($group) ?? throw new HttpException('PRINT_SALES_OPEN', 409);
            try {
                $this->snapshot->take($group, $deadline, $actorId, $now, !$supplement);
            } catch (HttpException $error) {
                if (!$supplement || 'NOTHING_TO_BUILD' !== $error->getMessage()) {
                    throw $error;
                }
            }
        }
        $delivered = [];
        if ($supplement) {
            foreach ($history as $previous) {
                foreach ($previous->groups as $saved) {
                    foreach ($saved->sourceVersionIds as $id) {
                        $delivered[$id] = true;
                    }
                }
            }
        }
        $versions = $this->jobs->byGroups(array_map(static fn(GroupDirectoryItemOutputDto $group): int => $group->nativeId, $groups));
        $sources = [];
        $signature = [];
        foreach ($groups as $group) {
            $deadline = $this->groups->deadline($group)?->format('Y-m-d H:i:s');
            $selected = array_values(array_filter($versions[$group->nativeId] ?? [], static fn(ArchiveVersion $job): bool => $job->deadlineAt->format('Y-m-d H:i:s') === $deadline && !isset($delivered[$job->id])));
            usort($selected, static fn(ArchiveVersion $a, ArchiveVersion $b): int => $a->id <=> $b->id);
            $sources[$group->id] = $selected;
            $signature[$group->id] = [$deadline, array_map(static fn(ArchiveVersion $job): int => $job->id, $selected)];
        }
        $kind = $supplement ? 'supplement' : 'full';
        $fingerprint = hash('sha256', json_encode($signature, JSON_THROW_ON_ERROR));
        foreach ($history as $previous) {
            if ($previous->kind === $kind && $previous->fingerprint === $fingerprint) {
                return null;
            }
        }
        $root = $this->layout->folders([$institution->id => $institution->name], 'Учреждение')[$institution->id];
        $names = [];
        foreach ($groups as $group) {
            $names[$group->id] = $group->name;
        }
        $folders = $this->layout->folders($names, 'Группа');
        $savedGroups = [];
        $seen = [];
        $files = [];
        $lineCount = 0;
        $printCount = 0;
        foreach ($groups as $group) {
            $lines = [];
            $names = [];
            $sourceIds = [];
            foreach ($sources[$group->id] as $job) {
                $sourceIds[] = $job->id;
                foreach ($this->jobs->lines($job->id) as $line) {
                    if (isset($seen[$line->orderLineId])) {
                        continue;
                    }
                    $seen[$line->orderLineId] = true;
                    $lines[] = $line;
                    if (2 === $job->layoutVersion) {
                        $names[$line->photoId] ??= basename($line->entry);
                    }
                }
            }
            $missingNames = [];
            foreach ($lines as $line) {
                if (!isset($names[$line->photoId])) {
                    $missingNames[$line->photoId] = $line->photoId;
                }
            }
            foreach ($this->filenames->filenames(array_values($missingNames)) as $photoId => $filename) {
                $names[$photoId] = $filename;
            }
            $entries = [];
            foreach ($this->layout->arrange($lines, $names) as $line) {
                $path = $root . '/' . $folders[$group->id] . '/' . $line->entry;
                if (1024 < mb_strlen($path)) {
                    throw new HttpException('UNSAFE_FILENAME', 409);
                }
                $entries[$line->orderLineId] = $path;
                $files[$path] = true;
                ++$lineCount;
                $printCount += $line->prints;
            }
            $savedGroups[] = new InstitutionArchiveGroup(id: $group->id, name: $group->name, deadline: (string)$this->groups->deadline($group)?->format('Y-m-d H:i:s'), sourceVersionIds: $sourceIds, entries: $entries);
        }
        if ($supplement && 0 === $lineCount) {
            throw new HttpException('NOTHING_TO_BUILD', 409);
        }

        return $this->releases->create(new NewInstitutionArchive(
            publicId: $this->ids->uuid(),
            institutionId: $institution->nativeId,
            institutionPublicId: $institution->id,
            kind: $kind,
            fingerprint: $fingerprint,
            groups: $savedGroups,
            lines: $lineCount,
            files: count($files),
            prints: $printCount,
            createdAt: $now,
        ), $request);
    }

    /** @return list<ArchiveLine> */
    public function lines(InstitutionArchive $archive): array
    {
        $lines = [];
        $seen = [];
        foreach ($archive->groups as $group) {
            foreach ($group->sourceVersionIds as $source) {
                foreach ($this->jobs->lines($source) as $line) {
                    if (isset($seen[$line->orderLineId])) {
                        continue;
                    }
                    $entry = $group->entries[$line->orderLineId] ?? throw new ProductionStorageException('Incomplete institution snapshot.');
                    $seen[$line->orderLineId] = true;
                    $lines[] = new ArchiveLine(
                        orderLineId: $line->orderLineId,
                        orderId: $line->orderId,
                        orderNumber: $line->orderNumber,
                        paidAt: $line->paidAt,
                        audience: $line->audience,
                        childCode: $line->childCode,
                        photoId: $line->photoId,
                        photoCode: $line->photoCode,
                        productId: $line->productId,
                        productName: $line->productName,
                        productFormat: $line->productFormat,
                        printCount: $line->printCount,
                        quantity: $line->quantity,
                        prints: $line->prints,
                        entry: $entry,
                    );
                }
            }
        }
        if (count($lines) !== $archive->lines) {
            throw new ProductionStorageException('Institution source rows are missing.');
        }

        return $lines;
    }
}
