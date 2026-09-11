<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Rebit\Share\Shared\Interface\NormalizerInterface;

/**
 * Основной класс для нормализации данных.
 * Нормализует любые входящие данные для дальнейшей сериализации.
 */
final readonly class CommonNormalizer implements NormalizerInterface
{
    public function __construct(
        private NormalizerInterface $objectNormalizer,
        private NormalizerInterface $scalarNormalizer,
    ) {}

    public function normalize(mixed $data): mixed
    {
        return match (true) {
            is_object($data) => $this->objectNormalizer->normalize($data),
            is_array($data) => array_map(fn($item) => $this->normalize($item), $data),
            default => $this->scalarNormalizer->normalize($data),
        };
    }
}
