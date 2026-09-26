<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Service;

use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;
use Morefoto\Files\Application\Files\Dto\DownloadOutputDto;
use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Domain\Download\ValueObject\Download;

/** Показывает покупателю состояние загрузки с учётом текущего права: истёкший срок или потерянное право делают готовую загрузку истёкшей.
 * Только готовая и действующая загрузка получает подпись короткой ссылки на содержимое.
 */
final readonly class DownloadView
{
    public function __construct(private FileAccessPolicy $policy, private DownloadTokenInterface $tokens) {}

    public function output(Download $download, FileAccessOutputDto $access, \DateTimeImmutable $now): DownloadOutputDto
    {
        $status = $download->status;
        if (DownloadStatusEnum::READY === $status
            && (FilesStateEnum::AVAILABLE !== $access->state || null === $download->expiresAt || $now >= $download->expiresAt)) {
            $status = DownloadStatusEnum::EXPIRED;
        }
        $ready = DownloadStatusEnum::READY === $status && null !== $download->expiresAt;

        return new DownloadOutputDto(
            id: $download->publicId,
            kind: $download->kind->value,
            status: $status->value,
            expiresAt: $ready ? $download->expiresAt : null,
            filename: $download->filename,
            error: DownloadStatusEnum::FAILED === $status ? $download->errorCode : null,
            contentToken: $ready ? $this->tokens->issue($download->publicId, $this->policy->linkExpiresAt($now, $download->expiresAt)) : null,
        );
    }
}
