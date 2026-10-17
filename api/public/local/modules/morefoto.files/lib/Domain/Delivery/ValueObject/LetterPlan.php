<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\ValueObject;

/** Кадры заказа, разложенные по письмам, и кадры, которые слишком велики для письма и остаются только по ссылке. */
final readonly class LetterPlan
{
    /**
     * @param list<list<LetterFile>> $letters  письма по порядку, хотя бы одно; пустое — письмо со ссылкой без вложений
     * @param list<LetterFile>       $linkOnly кадры выше предела письма
     */
    public function __construct(
        public array $letters,
        public array $linkOnly,
    ) {}
}
