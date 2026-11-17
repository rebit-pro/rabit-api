<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Service;

use Morefoto\Files\Application\Institution\Dto\DigitalArchiveOutputDto;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Проецирует состояние сохранённой выгрузки без внутренних путей; срок закрывает ZIP до очередного запуска уборки.
 * @phpstan-import-type ArchiveSummary from DigitalArchiveRepositoryInterface
 */
final readonly class DigitalArchiveView
{
    public function __construct(private ClockInterface $clock) {}

    public function describe(DigitalArchive $archive): DigitalArchiveOutputDto
    {
        return $this->summary([
            'id' => $archive->id, 'status' => $archive->status, 'files' => count($archive->files), 'attempts' => $archive->attempts,
            'bytes' => $archive->bytes, 'error' => $archive->error, 'capturedAt' => $archive->capturedAt,
            'readyAt' => $archive->readyAt, 'expiresAt' => $archive->expiresAt,
        ]);
    }

    /** @param ArchiveSummary $archive */
    public function summary(array $archive): DigitalArchiveOutputDto
    {
        $status = 'ready' === $archive['status'] && null !== $archive['expiresAt'] && $archive['expiresAt'] <= $this->clock->now()
            ? 'expired' : $archive['status'];

        return new DigitalArchiveOutputDto(
            id: $archive['id'],
            status: $status,
            files: $archive['files'],
            attempts: $archive['attempts'],
            bytes: $archive['bytes'],
            error: $archive['error'],
            capturedAt: $archive['capturedAt'],
            readyAt: $archive['readyAt'],
            expiresAt: $archive['expiresAt'],
        );
    }
}
