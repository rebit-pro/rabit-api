<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Access\Enum;

enum AccessLinkPurposeEnum: string
{
    case INVITE = 'invite';
    case RESET = 'reset';
}
