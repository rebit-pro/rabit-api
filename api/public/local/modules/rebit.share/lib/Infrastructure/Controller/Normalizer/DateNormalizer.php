<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Bitrix\Main\Type\Date;
use Rebit\Share\Shared\Interface\NormalizerInterface;

/**
 * Класс нормализует Date
 */
final class DateNormalizer implements NormalizerInterface
{
    /**
     * @param Date $data
     */
    public function normalize(mixed $data): string
    {
        return $data->format('Y-m-d');
    }
}
