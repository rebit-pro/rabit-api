<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Institution\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class DigitalArchiveResultDto implements ResponseDtoInterface
{
    public function __construct(
        public string $id,
        public string $status,
        public int $files,
        public int $attempts,
        public ?int $bytes,
        public ?string $error,
        public string $capturedAt,
        public ?string $readyAt,
        public ?string $expiresAt,
    ) {}
}
