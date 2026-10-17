<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\ValueObject;

/** Одно письмо доставки: номер по порядку, вложения по сохранённому плану и операция H1 после постановки в очередь. */
final readonly class DeliveryPart
{
    /**
     * @param int              $number номер письма, с 1
     * @param list<LetterFile> $files  вложения письма
     */
    public function __construct(
        public int $number,
        public array $files,
        public ?string $operationId,
    ) {}
}
