<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;

interface StaffQuestionContextInterface
{
    /** Только активные head и teacher; остальные роли — 403 FORBIDDEN. */
    public function resolve(int $userId): StaffQuestionContextDto;
}
