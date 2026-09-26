<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Consent\Enum;

/** Сценарий, в котором принимаются документы; определяет их набор и смысл ID субъекта. */
enum ConsentContextEnum: string
{
    /** Субъект — внутренний ID заказа. */
    case ORDER = 'order';
    /** Субъект — ID учётной записи сотрудника. */
    case STAFF = 'staff';
}
