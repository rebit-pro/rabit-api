<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Helper;

use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class DtoToArrayNormalizer
{
    use NormalizerAwareTrait;

    private static ?ObjectNormalizer $objectNormalizer = null;

    public static function normalize(array|object $object, $format = null, array $context = []): array
    {
        if (null === self::$objectNormalizer) {
            self::init();
        }

        $data = self::$objectNormalizer->normalize($object, $format, $context);

        // Ищем свойства, которые сами являются DTO и рекурсивно нормализуем
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $data[$key] = self::normalize($value, $format, $context);
            }
        }

        return $data;
    }

    private static function init(): void
    {
        self::$objectNormalizer = new ObjectNormalizer();
    }
}
