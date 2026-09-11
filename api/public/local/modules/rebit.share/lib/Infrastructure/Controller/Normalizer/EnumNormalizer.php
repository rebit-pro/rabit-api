<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Rebit\Share\Shared\Interface\NormalizerInterface;

/**
 * Класс нормализует Enum
 */
final class EnumNormalizer implements NormalizerInterface
{
    /**
     * @param \BackedEnum $data
     */
    public function normalize(mixed $data): array
    {
        return [
            'value' => $data->value,
            'name' => $data->name,
        ];
    }
}
