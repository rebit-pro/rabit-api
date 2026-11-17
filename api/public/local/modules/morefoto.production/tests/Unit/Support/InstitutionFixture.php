<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit\Support;

use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveBuilder;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveView;
use Morefoto\Production\Application\Institution\Service\InstitutionGroups;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Application\Institution\UseCase\CreateInstitutionSupplementUseCase;
use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\ExportInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\GetInstitutionProductionUseCase;
use Morefoto\Production\Application\Institution\UseCase\RenewInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\RunInstitutionProductionUseCase;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Infrastructure\File\CsvManifestWriter;
use Morefoto\Production\Infrastructure\File\ZipArchiveWriter;
use Morefoto\Production\Infrastructure\Id\ProductionIdGenerator;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectorySummaryOutputDto;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;

/** Настоящие правила L1, исходные файлы и ZIP; внешние поставщики и хранилище метаданных изолированы. */
final class InstitutionFixture implements GroupDirectoryInterface
{
    public const string INSTITUTION = '33333333-3333-4333-8333-333333333337';
    public ProductionFixture $base;
    public InMemoryInstitutionArchives $archives;
    public PrintInstitutionDirectoryInterface $institutions;
    public InstitutionGroups $groups;
    public InstitutionSnapshot $snapshot;
    public InstitutionArchiveAccess $access;
    public InstitutionArchiveView $view;

    public function __construct()
    {
        $this->base = new ProductionFixture();
        $this->archives = new InMemoryInstitutionArchives();
        $this->institutions = new class implements PrintInstitutionDirectoryInterface {
            public function find(string $id): ?PrintInstitutionOutputDto
            {
                return InstitutionFixture::INSTITUTION === $id ? new PrintInstitutionOutputDto(7, $id, 'Солнышко') : null;
            }

            public function after(int $nativeId, int $limit): array
            {
                return 7 > $nativeId ? [$this->find(InstitutionFixture::INSTITUTION)] : [];
            }
        };
        $this->groups = new InstitutionGroups($this, new ArchivePolicy());
        $this->access = new InstitutionArchiveAccess($this->base, $this->institutions, $this->archives);
        $this->view = new InstitutionArchiveView($this->base->storage(), $this->base);
        $snapshot = new VersionSnapshot($this->base->archives, $this->base, new ArchiveLayout(), $this->base->storage(), new ProductionIdGenerator(), $this->base);
        $this->snapshot = new InstitutionSnapshot($this->archives, $this->base->archives, $snapshot, $this->groups, new ArchiveLayout(), $this->base, new ProductionIdGenerator(), $this->base);
    }

    public function builder(?ArchiveWriterInterface $writer = null, ?ArchiveStorageInterface $storage = null): InstitutionArchiveBuilder
    {
        return new InstitutionArchiveBuilder(
            $this->archives,
            $this->snapshot,
            $this->groups,
            $this->base,
            $writer ?? new ZipArchiveWriter(new AtomicZipWriter()),
            new CsvManifestWriter(),
            $storage ?? $this->base->storage(),
            new ProductionIdGenerator(),
            new ArchivePolicy(),
            $this->base,
        );
    }

    public function run(): RunInstitutionProductionUseCase
    {
        return new RunInstitutionProductionUseCase($this->archives, $this->institutions, $this->snapshot, $this->builder(), $this->base->storage(), $this->base);
    }

    public function get(): GetInstitutionProductionUseCase
    {
        return new GetInstitutionProductionUseCase($this->access, $this->groups, $this->archives, $this->base->archives, $this->base, $this->view, $this->base);
    }

    public function download(): DownloadInstitutionArchiveUseCase
    {
        return new DownloadInstitutionArchiveUseCase($this->access, $this->base->links, $this->base->storage(), $this->base);
    }

    public function supplement(): CreateInstitutionSupplementUseCase
    {
        return new CreateInstitutionSupplementUseCase($this->access, $this->snapshot, $this->view);
    }

    public function renew(): RenewInstitutionArchiveUseCase
    {
        return new RenewInstitutionArchiveUseCase($this->access, $this->archives, $this->base->storage(), $this->view, $this->base);
    }

    public function export(): ExportInstitutionArchiveUseCase
    {
        return new ExportInstitutionArchiveUseCase($this->access, $this->snapshot, new CsvManifestWriter());
    }

    public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto
    {
        $items = array_values(array_filter($this->base->groups, static fn(GroupDirectoryItemOutputDto $group): bool => $group->institutionId === $query->institutionId));

        return new GroupDirectoryPageOutputDto(array_slice($items, ($query->page - 1) * $query->pageSize, $query->pageSize), count($items));
    }

    public function find(string $groupId): ?GroupDirectoryItemOutputDto
    {
        return $this->base->find($groupId);
    }

    public function summary(GroupDirectoryQueryInputDto $query, int $closingWithinHours): GroupDirectorySummaryOutputDto
    {
        throw new \LogicException('Not used.');
    }

    public function nativeIds(GroupDirectoryQueryInputDto $query): array
    {
        throw new \LogicException('Not used.');
    }
}
