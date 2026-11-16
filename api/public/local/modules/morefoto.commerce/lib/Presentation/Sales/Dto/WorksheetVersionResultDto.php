<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetVersionResultDto implements ResultDtoInterface
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
