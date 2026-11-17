<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Dto;

final readonly class InstitutionVersionOutputDto
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
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
