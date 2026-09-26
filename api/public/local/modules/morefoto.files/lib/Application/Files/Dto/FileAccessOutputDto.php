<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Dto;

use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;

final readonly class FileAccessOutputDto
{
    /**
     * @param array<string, EntitledFileOutputDto> $files доступные файлы по публичному ID кадра; пусто, если право не действует
     */
    public function __construct(
        public int $orderId,
        public string $orderPublicId,
        public string $orderNumber,
        public FilesStateEnum $state,
        public ?\DateTimeImmutable $availableUntil,
        public array $files,
    ) {}
}
