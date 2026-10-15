<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class VersionResultDto implements ResultDtoInterface
{
    /** @param list<ProductResultDto> $products */
    public function __construct(
        public string $id,
        public string $groupId,
        public int $number,
        public string $status,
        public int $lines,
        public int $files,
        public int $prints,
        public int $waitingOrders,
        public array $products,
        public bool $manual,
        public string $createdAt,
        public ?string $readyAt,
        public ?string $expiresAt,
        public ?int $bytes,
        public ?string $error,
        public ?string $archiveUrl,
        public string $exportUrl,
    ) {}
}
