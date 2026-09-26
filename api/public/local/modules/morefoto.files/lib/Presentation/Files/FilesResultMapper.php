<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files;

use Morefoto\Files\Application\Files\Dto\DownloadOutputDto;
use Morefoto\Files\Application\Files\Dto\EntitledFileOutputDto;
use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Presentation\Files\Result\Dto\DownloadResultDto;
use Morefoto\Files\Presentation\Files\Result\Dto\OrderFileResultDto;
use Morefoto\Files\Presentation\Files\Result\Dto\OrderFilesResultDto;

/** Даты — по Москве в ATOM, как во всех ответах заказа; ссылка на содержимое относительна к API. */
final readonly class FilesResultMapper
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function files(FileAccessOutputDto $output): OrderFilesResultDto
    {
        $items = array_values(array_map($this->file(...), $output->files));
        $bytes = 0;
        foreach ($items as $item) {
            $bytes += $item->bytes;
        }

        return new OrderFilesResultDto(
            state: $output->state->value,
            expiresAt: $this->moment($output->availableUntil),
            canDownload: FilesStateEnum::AVAILABLE === $output->state,
            totalBytes: $bytes,
            items: $items,
        );
    }

    public function download(DownloadOutputDto $output): DownloadResultDto
    {
        return new DownloadResultDto(
            id: $output->id,
            kind: $output->kind,
            status: $output->status,
            expiresAt: $this->moment($output->expiresAt),
            filename: $output->filename,
            error: $output->error,
            contentUrl: null === $output->contentToken ? null
                : '/api/v1/public/orders/current/downloads/' . $output->id . '/content?token=' . rawurlencode($output->contentToken),
        );
    }

    private function file(EntitledFileOutputDto $file): OrderFileResultDto
    {
        return new OrderFileResultDto($file->photoId, $file->code, $file->childCode, $file->filename, $file->mimeType, $file->bytes);
    }

    private function moment(?\DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(DATE_ATOM);
    }
}
