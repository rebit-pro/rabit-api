<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Enum;

enum LinkActionEnum: string
{
    case READ = 'read';
    case PREPARE = 'prepare';
    case TRANSMIT = 'transmit';
    case CORRECT = 'correct';
}
