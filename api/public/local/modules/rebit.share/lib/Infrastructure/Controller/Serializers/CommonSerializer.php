<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Serializers;

use Rebit\Share\Shared\Interface\NormalizerInterface;
use Rebit\Share\Infrastructure\Interface\SerializerInterface;
use Rebit\Share\Infrastructure\Helpers\JsonSerializerHelper;

/**
 * Сериализует данные в JSON-строку. Основной сериализатор.
 */
final readonly class CommonSerializer implements SerializerInterface
{
    public function __construct(
        private NormalizerInterface $commonNormalizer,
    ) {}

    /**
     * Создает дефолтовый сериализатор.
     * При необходимости частой иной сериализации можно добавить тут аналогичные кастомные конструкторы.
     */
    public static function createDefault(): SerializerInterface
    {
        // Логика создания дефолта перемещена в helper — здесь остаётся совместимый API.
        return JsonSerializerHelper::createDefaultSerializer();
    }

    public function serialize(
        mixed $data,
        int $jsonOptions = JsonSerializerHelper::DEFAULT_JSON_OPTIONS,
    ): string {
        // Делегируем реальную сериализацию helper-у.
        return JsonSerializerHelper::serialize($this->commonNormalizer, $data, $jsonOptions);
    }
}
