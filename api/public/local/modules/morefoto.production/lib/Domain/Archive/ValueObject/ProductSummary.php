<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\ValueObject;

/** Продукция в составе версии: сколько файлов и отпечатков ушло в её папку. */
final readonly class ProductSummary
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $format,
        public int $files,
        public int $prints,
    ) {}
}
