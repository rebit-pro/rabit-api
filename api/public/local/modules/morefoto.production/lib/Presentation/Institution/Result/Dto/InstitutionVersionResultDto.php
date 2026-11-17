<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class InstitutionVersionResultDto implements ResponseDtoInterface
{
    /** @param list<string> $groups */
    public function __construct(
        public string $id,
        public int $number,
        public string $kind,
        public string $status,
        public int $lines,
        public int $files,
        public int $prints,
        public int $generation,
        public array $groups,
        public ?string $error,
        public ?int $bytes,
        public string $createdAt,
        public ?string $readyAt,
        public ?string $expiresAt,
    ) {}
}
