<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class GroupProductionStatusOutputDto
{
    /**
     * @param string   $state   состояние блока «Печать» группы
     * @param null|int $version номер последней версии
     */
    public function __construct(
        public string $groupId,
        public string $state,
        public ?int $version,
    ) {}
}
