<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Production\Dto;

final readonly class PrintVersionOutputDto
{
    /** @param list<string> $groupIds публичные ID групп сохранённой версии */
    public function __construct(
        public string $type,
        public string $id,
        public string $institutionId,
        public array $groupIds,
        public int $number,
        public string $kind,
        public string $status,
        public int $layoutVersion,
        public string $createdAt,
        public ?string $expiresAt,
        public int $files,
        public int $prints,
    ) {}
}
