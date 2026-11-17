<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Dto;

final readonly class DigitalArchiveOutputDto
{
    public function __construct(
        public string $id,
        public string $status,
        public int $files,
        public int $attempts,
        public ?int $bytes,
        public ?string $error,
        public \DateTimeImmutable $capturedAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
