<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Production\Dto;

final readonly class PrintLineOutputDto
{
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $lineId,
        public string $orderId,
        public string $photoId,
        public string $productId,
        public string $productName,
        public string $productFormat,
        public int $quantity,
        public int $printCount,
        public int $prints,
        public string $filename,
        public string $path,
        public int $printsInFile,
    ) {}
}
