<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderFilesResultDto implements ResultDtoInterface
{
    /** @param list<OrderFileResultDto> $items */
    public function __construct(
        public string $state,
        public ?string $expiresAt,
        public bool $canDownload,
        public int $totalBytes,
        public array $items,
    ) {}
}
