<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Institution;

use Morefoto\Files\Application\Institution\Dto\DigitalArchiveLinkOutputDto;
use Morefoto\Files\Application\Institution\Dto\DigitalArchiveListOutputDto;
use Morefoto\Files\Application\Institution\Dto\DigitalArchiveOutputDto;
use Morefoto\Files\Presentation\Institution\Result\Dto\DigitalArchiveLinkResultDto;
use Morefoto\Files\Presentation\Institution\Result\Dto\DigitalArchiveListResultDto;
use Morefoto\Files\Presentation\Institution\Result\Dto\DigitalArchiveResultDto;

/** Оформляет публичные состояния/даты служебного ZIP и короткую ссылку, не раскрывая снимок и приватные пути. */
final readonly class DigitalArchiveResultMapper
{
    public function archive(DigitalArchiveOutputDto $output): DigitalArchiveResultDto
    {
        return new DigitalArchiveResultDto(
            id: $output->id,
            status: $output->status,
            files: $output->files,
            attempts: $output->attempts,
            bytes: $output->bytes,
            error: $output->error,
            capturedAt: (string)$this->moment($output->capturedAt),
            readyAt: $this->moment($output->readyAt),
            expiresAt: $this->moment($output->expiresAt),
        );
    }

    public function listing(DigitalArchiveListOutputDto $output): DigitalArchiveListResultDto
    {
        return new DigitalArchiveListResultDto($output->canCreate, array_map($this->archive(...), $output->items));
    }

    public function link(DigitalArchiveLinkOutputDto $output): DigitalArchiveLinkResultDto
    {
        return new DigitalArchiveLinkResultDto('/api/v1/institution-digital-archives/content/' . rawurlencode($output->ticket), (string)$this->moment($output->expiresAt));
    }

    private function moment(?\DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new \DateTimeZone('Europe/Moscow'))->format(DATE_ATOM);
    }
}
