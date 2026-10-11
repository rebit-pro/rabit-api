<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupDirectorySummaryOutputDto
{
    /**
     * @param int $open        печать и электронные фото продаются
     * @param int $digital     печать закрыта, электронные фото ещё продаются
     * @param int $closingSoon открытые группы, у которых приём печати закрывается в заданный горизонт
     */
    public function __construct(
        public int $preparing,
        public int $open,
        public int $digital,
        public int $closed,
        public int $closingSoon,
        public string $referenceNow,
    ) {}
}
