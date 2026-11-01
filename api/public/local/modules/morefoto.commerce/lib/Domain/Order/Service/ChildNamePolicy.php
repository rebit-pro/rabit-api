<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Service;

use Morefoto\Commerce\Domain\Order\ValueObject\OrderChildName;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет имя и фамилию ребёнка при новом оформлении и сохраняет нормализованную пару на уровне заказа.
 * Старые подтверждённые попытки возвращаются до этой проверки, поэтому их повтор не требует новых полей.
 */
final readonly class ChildNamePolicy
{
    public function accept(?string $firstName, ?string $lastName): OrderChildName
    {
        return new OrderChildName(
            $this->part($firstName, 'INVALID_CHILD_FIRST_NAME'),
            $this->part($lastName, 'INVALID_CHILD_LAST_NAME'),
        );
    }

    private function part(?string $value, string $error): string
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value || 100 < mb_strlen($value) || 1 !== preg_match('/[^\s\p{Z}]/u', $value)
            || 1 !== preg_match('/^[^\p{C}]+$/uD', $value)) {
            throw new HttpException($error, 422);
        }

        return $value;
    }
}
