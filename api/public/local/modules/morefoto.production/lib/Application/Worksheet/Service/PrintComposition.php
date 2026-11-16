<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Worksheet\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Production\Dto\PrintCompositionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintLineOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionsOutputDto;
use Rebit\Share\Contracts\Production\PrintCompositionInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Открывает ведомости неизменяемый состав конкретного печатного выпуска в действующей области сотрудника.
 * Возвращает ровно сохранённые пути и вклад исходных покупок в общий файл; архив и задания печати не создаёт.
 */
final readonly class PrintComposition implements PrintCompositionInterface
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private ArchiveRepositoryInterface $jobs,
        private InstitutionArchiveAccess $institutions,
        private InstitutionArchiveRepositoryInterface $releases,
        private InstitutionSnapshot $snapshot,
        private ArchiveStorageInterface $storage,
        private ClockInterface $clock,
    ) {}

    public function available(int $actorId, ?string $groupId, ?string $institutionId): PrintVersionsOutputDto
    {
        try {
            $scope = $this->access->scope($actorId);
        } catch (HttpException $error) {
            if (403 !== $error->getCode()) {
                throw $error;
            }

            return new PrintVersionsOutputDto(false, []);
        }
        $versions = [];
        if (null !== $groupId) {
            $group = $this->lookup->group($scope, $groupId);
            foreach ($this->jobs->byGroups([$group->nativeId])[$group->nativeId] ?? [] as $job) {
                if ($job->institutionId !== $group->institutionNativeId || !$this->lookup->visible($scope, $job->institutionId)) {
                    continue;
                }
                $versions[] = $this->groupVersion($job, $group->institutionId);
            }
        } elseif (null !== $institutionId) {
            $institution = $this->institutions->institution($actorId, $institutionId);
            foreach ($this->releases->history($institution->nativeId) as $archive) {
                $versions[] = $this->institutionVersion($archive);
            }
        }
        usort($versions, static fn(PrintVersionOutputDto $a, PrintVersionOutputDto $b): int => [$b->createdAt, $b->number, $b->id] <=> [$a->createdAt, $a->number, $a->id]);

        return new PrintVersionsOutputDto(true, $versions);
    }

    public function read(int $actorId, string $type, string $id): PrintCompositionOutputDto
    {
        $scope = $this->access->scope($actorId);
        /** @var array<string, array{id: string, name: string}> $owners */
        $owners = [];
        if ('group' === $type) {
            $job = $this->lookup->version($scope, $id);
            if (self::MAX_LINES < $job->linesCount) {
                throw new HttpException('WORKSHEET_TOO_LARGE', 422);
            }
            $this->ready($job->status, $job->expiresAt, $job->archivePath);
            if (2 !== $job->layoutVersion) {
                throw new HttpException('WORKSHEET_LEGACY_LAYOUT', 409);
            }
            $group = $this->lookup->group($scope, $job->groupPublicId);
            if ($job->institutionId !== $group->institutionNativeId) {
                throw new HttpException('VERSION_NOT_FOUND', 404);
            }
            $version = $this->groupVersion($job, $group->institutionId);
            $lines = $this->jobs->lines($job->id);
            foreach ($lines as $line) {
                $owners[$line->orderLineId] = ['id' => $group->id, 'name' => $group->name];
            }
        } elseif ('institution' === $type) {
            $archive = $this->institutions->archive($actorId, $id);
            if (self::MAX_LINES < $archive->lines) {
                throw new HttpException('WORKSHEET_TOO_LARGE', 422);
            }
            $this->ready($archive->status, $archive->expiresAt, $archive->archivePath);
            $version = $this->institutionVersion($archive);
            $lines = $this->snapshot->lines($archive);
            foreach ($archive->groups as $group) {
                foreach ($group->entries as $lineId => $entry) {
                    $owners[$lineId] = ['id' => $group->id, 'name' => $group->name];
                }
            }
        } else {
            throw new HttpException('INVALID_WORKSHEET_SELECTION', 422);
        }

        return new PrintCompositionOutputDto($version, $this->lines($lines, $owners, $version));
    }

    private function ready(VersionStatusEnum $status, ?\DateTimeImmutable $expires, ?string $path): void
    {
        if (VersionStatusEnum::READY !== $status || null === $expires || $expires <= $this->clock->now()
            || null === $path || '' === $path || !$this->storage->exists($path)) {
            throw new HttpException('WORKSHEET_ARCHIVE_NOT_READY', 409);
        }
    }

    private function groupVersion(ArchiveVersion $job, string $institutionId): PrintVersionOutputDto
    {
        return new PrintVersionOutputDto(
            type: 'group',
            id: $job->publicId,
            institutionId: $institutionId,
            groupIds: [$job->groupPublicId],
            number: $job->number,
            kind: 1 === $job->number ? 'full' : 'supplement',
            status: $job->status->value,
            layoutVersion: $job->layoutVersion,
            createdAt: $job->createdAt->format(DATE_ATOM),
            expiresAt: $job->expiresAt?->format(DATE_ATOM),
            files: $job->filesCount,
            prints: $job->printsCount,
        );
    }

    private function institutionVersion(InstitutionArchive $archive): PrintVersionOutputDto
    {
        $groupIds = [];
        foreach ($archive->groups as $group) {
            $groupIds[] = $group->id;
        }

        return new PrintVersionOutputDto(
            type: 'institution',
            id: $archive->publicId,
            institutionId: $archive->institutionPublicId,
            groupIds: $groupIds,
            number: $archive->number,
            kind: $archive->kind,
            status: $archive->status->value,
            layoutVersion: 2,
            createdAt: $archive->createdAt->format(DATE_ATOM),
            expiresAt: $archive->expiresAt?->format(DATE_ATOM),
            files: $archive->files,
            prints: $archive->prints,
        );
    }

    /**
     * @param list<ArchiveLine>                              $lines
     * @param array<string, array{id: string, name: string}> $owners
     *
     * @return list<PrintLineOutputDto>
     */
    private function lines(array $lines, array $owners, PrintVersionOutputDto $version): array
    {
        $files = [];
        $prints = 0;
        $seen = [];
        foreach ($lines as $line) {
            if (isset($seen[$line->orderLineId]) || '' === $line->entry || !isset($owners[$line->orderLineId])) {
                throw new HttpException('WORKSHEET_SOURCE_MISSING', 409);
            }
            $seen[$line->orderLineId] = true;
            $files[$line->entry] = ($files[$line->entry] ?? 0) + $line->prints;
            $prints += $line->prints;
        }
        if (count($files) !== $version->files || $prints !== $version->prints) {
            throw new HttpException('WORKSHEET_SOURCE_MISSING', 409);
        }
        $result = [];
        foreach ($lines as $line) {
            $owner = $owners[$line->orderLineId];
            $result[] = new PrintLineOutputDto(
                groupId: $owner['id'],
                groupName: $owner['name'],
                lineId: $line->orderLineId,
                orderId: $line->orderId,
                photoId: $line->photoId,
                productId: $line->productId,
                productName: $line->productName,
                productFormat: $line->productFormat,
                quantity: $line->quantity,
                printCount: $line->printCount,
                prints: $line->prints,
                filename: basename($line->entry),
                path: $line->entry,
                printsInFile: $files[$line->entry],
            );
        }

        return $result;
    }
}
