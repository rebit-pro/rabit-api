<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\ValueObject;

/** Позиция автоматического планировщика: группы со сроком печати до `sinceAt` собираются только вручную (L1-DEC-08). */
final readonly class ScheduleCursor
{
    public function __construct(
        public \DateTimeImmutable $sinceAt,
        public \DateTimeImmutable $cursorAt,
        public int $cursorGroupId,
    ) {}
}
