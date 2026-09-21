<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\StaffChildOutputDto;

interface StaffChildReferenceInterface
{
    /** Разрешает код ребёнка или назначенного снимка внутри уже проверенной группы/съёмки. */
    public function resolve(int $shootId, int $groupId, string $code): StaffChildOutputDto;
}
