<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Rebit\Share\Infrastructure\Controller\Normalizer\CommonNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\DateNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\DateTimeNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\EnumNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\ObjectNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\ScalarNormalizer;
use Rebit\Share\Shared\Interface\NormalizerInterface;
use Rebit\Share\Infrastructure\Interface\SerializerInterface;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;

/**
 * Вспомогательный класс для JSON-сериализации и создания дефолтового наборa нормализаторов/сериализаторов.
 * Нужен, чтобы использовать кастомную сериализацию в разных местах.
 */
final class JsonSerializerHelper
{
    public const DEFAULT_JSON_OPTIONS
        = JSON_THROW_ON_ERROR
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE;

    /**
     * Возвращает дефолтовый Normalizer (ранее создавался в CommonSerializer::createDefault).
     */
    public static function createDefaultNormalizer(): NormalizerInterface
    {
        return new CommonNormalizer(
            new ObjectNormalizer(
                new EnumNormalizer(),
                new DateTimeNormalizer(),
                new DateNormalizer(),
                new ScalarNormalizer(),
            ),
            new ScalarNormalizer(),
        );
    }

    /**
     * Возвращает дефолтовый Serializer (экземпляр CommonSerializer с дефолтовым нормализатором).
     * Это позволяет вызывать CommonSerializer::createDefault(), который делегирует сюда.
     */
    public static function createDefaultSerializer(): SerializerInterface
    {
        return new CommonSerializer(self::createDefaultNormalizer());
    }

    /**
     * Вынесенная логика JSON-сериализации: нормализует данные и кодирует в JSON.
     * Можно использовать напрямую, если нужно сериализовать через любой NormalizerInterface.
     */
    public static function serialize(NormalizerInterface $normalizer, mixed $data, int $jsonOptions = self::DEFAULT_JSON_OPTIONS): string
    {
        return json_encode($normalizer->normalize($data), $jsonOptions);
    }
}
