<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

/**
 * Проверяет запрос на сохранение условий продажи до записи: положительные версии и согласованные настройки подарка.
 * Выключенный подарок не имеет порога и льготы сотрудникам, включённый требует положительного порога.
 */
final readonly class ConditionsInputValidator
{
    public function validate(SaveConditionsInputDto $input): void
    {
        if (0 > $input->revision || 1 > $input->catalogRevision || (null !== $input->conditionsRevision && 1 > $input->conditionsRevision)) {
            throw new InvalidConditionsException('Positive condition and catalogue revisions are required.');
        }
        if (0 > $input->giftThreshold || 2147483647 < $input->giftThreshold) {
            throw new InvalidConditionsException('Gift threshold is outside the supported range.');
        }
        if ($input->giftEnabled && 0 === $input->giftThreshold) {
            throw new InvalidConditionsException('Gift threshold must be positive when the gift is enabled.');
        }
        if (!$input->giftEnabled && (0 !== $input->giftThreshold || $input->giftForStaff)) {
            throw new InvalidConditionsException('Disabled gift must have zero threshold and cannot be enabled for staff.');
        }
    }

    public function giftThreshold(SaveConditionsInputDto $input): int
    {
        return $input->giftEnabled ? $input->giftThreshold : 0;
    }
}
