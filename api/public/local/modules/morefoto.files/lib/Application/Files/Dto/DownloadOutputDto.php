<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Dto;

final readonly class DownloadOutputDto
{
    /**
     * @param null|string $contentToken подпись ссылки на содержимое; только у готовой загрузки при действующем праве
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public ?\DateTimeImmutable $expiresAt,
        public ?string $filename,
        public ?string $error,
        public ?string $contentToken,
    ) {}
}
