<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Bitrix\Main\Type\DateTime;
use Rebit\Share\Shared\Interface\NormalizerInterface;

/**
 * Класс нормализует DateTime
 */
final class DateTimeNormalizer implements NormalizerInterface
{
    /**
     * @param DateTime|\DateTimeInterface $data
     */
    public function normalize(mixed $data): string
    {
        return $data->format('Y-m-d H:i:s');
    }
}
