<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Service;

use Morefoto\Commerce\Application\Sales\Dto\SalesReportAreaOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOutputDto;
use Morefoto\Commerce\Application\Sales\Mapper\WorksheetPurchaseMapper;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionsOutputDto;
use Rebit\Share\Contracts\Production\PrintCompositionInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Собирает предварительную ведомость либо сверяет снимки покупок с явно выбранной сохранённой версией печати.
 * Область Commerce и права Production проверяются независимо; несовпавший состав и превышение объёма не скрываются.
 *
 * @phpstan-import-type WorksheetRow from SalesReportRepository
 */
final readonly class ProductionWorksheet
{
    private const int MAX_LINES = PrintCompositionInterface::MAX_LINES;

    public function __construct(
        private SalesReportRepository $sales,
        private PrintCompositionInterface $production,
        private WorksheetPurchaseMapper $mapper,
        private WorksheetFiles $files,
        private WorksheetAssembler $assembler,
        private ClockInterface $clock,
    ) {}

    public function build(int $actorId, SalesReportInputDto $input, SalesReportAreaOutputDto $scope): WorksheetOutputDto
    {
        $final = 'final' === $input->worksheetMode;
        if (!in_array($input->worksheetMode, ['preview', 'final'], true)
            || ($final && (null === $input->archiveType || null === $input->archiveId || '' === $input->archiveId))
            || (!$final && (null !== $input->archiveType || null !== $input->archiveId))) {
            throw new HttpException('INVALID_WORKSHEET_SELECTION', 422);
        }
        $allowed = [];
        foreach ($scope->groups as $group) {
            $allowed[$group->id] = true;
        }
        $composition = $final ? $this->production->read($actorId, (string)$input->archiveType, (string)$input->archiveId) : null;
        if (null !== $composition && !$this->inside($composition->version, $allowed)) {
            throw new HttpException('WORKSHEET_AREA_MISMATCH', 422);
        }
        $first = $scope->groups[0] ?? null;
        $institution = null !== $input->institutionId || null !== $input->shootId ? $first?->institutionId : null;
        $available = $this->production->available($actorId, $input->groupId, $institution);
        $versions = [];
        foreach ($available->versions as $version) {
            if ($this->inside($version, $allowed)) {
                $versions[] = $version;
            }
        }
        $purchases = [];
        $fixed = [];
        foreach (null === $composition ? [] : $composition->lines as $line) {
            $fixed[$line->lineId] = [new WorksheetFileOutputDto($line->photoId, $line->filename, $line->path, $line->printsInFile)];
        }
        $result = $this->sales->worksheet($scope->area, self::MAX_LINES + 1);
        while (false !== ($row = $result->fetch())) {
            if (self::MAX_LINES === count($purchases)) {
                throw new HttpException('WORKSHEET_TOO_LARGE', 422);
            }
            /** @var WorksheetRow $row */
            $purchases[] = $this->mapper->row($row);
        }

        return $this->assembler->assemble(
            $purchases,
            $this->files->resolve($purchases, $fixed),
            $scope->groups,
            $composition,
            new PrintVersionsOutputDto($available->allowed, $versions),
            $this->clock->now()->format(DATE_ATOM),
        );
    }

    /** @param array<string, true> $groups */
    private function inside(PrintVersionOutputDto $version, array $groups): bool
    {
        foreach ($version->groupIds as $id) {
            if (!isset($groups[$id])) {
                return false;
            }
        }

        return [] !== $version->groupIds;
    }
}
