<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\ValueObject;

/** Кадр права заказа в письме: имя как на странице заказа, тип, размер и путь оригинала, который прочитает отправка. */
final readonly class LetterFile
{
    public function __construct(
        public string $photoId,
        public string $name,
        public string $mimeType,
        public int $bytes,
        public string $path,
    ) {}
}
