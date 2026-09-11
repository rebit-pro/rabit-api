<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Rebit\Share\Infrastructure\Controller\Normalizer\CommonNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\DateNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\DateTimeNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\EnumNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\ObjectNormalizer;
use Rebit\Share\Infrastructure\Controller\Normalizer\ScalarNormalizer;

/**
 * Класс-обертка для упрощения работы с нормализацией данных
 */
final class NormalizerHelper
{
    private static ?CommonNormalizer $instance = null;

    /**
     * Получить экземпляр нормализатора
     */
    private static function getInstance(): CommonNormalizer
    {
        if (null === self::$instance) {
            self::$instance = self::createNormalizer();
        }

        return self::$instance;
    }

    /**
     * Нормализовать данные в массив
     */
    public static function normalize(mixed $data): mixed
    {
        return self::getInstance()->normalize($data);
    }

    /**
     * Создать настроенный экземпляр нормализатора
     */
    private static function createNormalizer(): CommonNormalizer
    {
        $scalarNormalizer = new ScalarNormalizer();

        $objectNormalizer = new ObjectNormalizer(
            new EnumNormalizer(),
            new DateTimeNormalizer(),
            new DateNormalizer(),
            $scalarNormalizer,
        );

        return new CommonNormalizer($objectNormalizer, $scalarNormalizer);
    }
}
