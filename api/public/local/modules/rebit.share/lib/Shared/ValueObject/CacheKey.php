<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\ValueObject;

final class CacheKey
{
    private string $cacheId;
    private string $cacheDir;
    /** @var string[] */
    private array $cacheTags;

    /**
     * @param string               $prefix Префикс для идентификации модуля/сущности
     * @param array<string, mixed> $params Параметры для формирования уникального ключа
     * @param string[]             $tags   Теги для инвалидации кэша
     *
     * @throws \JsonException
     */
    public function __construct(
        string $prefix,
        array $params = [],
        array $tags = [],
    ) {
        $this->cacheId = $this->buildCacheId($prefix, $params);
        $this->cacheDir = $this->buildCacheDir($prefix);
        $this->cacheTags = $this->buildCacheTags($prefix, $tags);
    }

    public function getCacheId(): string
    {
        return $this->cacheId;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @return string[]
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }

    /**
     * Возвращает основной тег для сброса всего кэша по префиксу.
     */
    public function getBaseTag(): string
    {
        return $this->cacheTags[0] ?? '';
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \JsonException
     */
    private function buildCacheId(string $prefix, array $params): string
    {
        if (0 === count($params)) {
            return $prefix;
        }

        ksort($params);
        $serialized = $this->serializeParams($params);

        return $prefix . '_' . md5($serialized);
    }

    private function buildCacheDir(string $prefix): string
    {
        $parts = explode('_', $prefix);
        $path = implode('/', array_map('strtolower', $parts));

        return '/rebit/' . $path;
    }

    /**
     * @param string[] $tags
     *
     * @return string[]
     */
    private function buildCacheTags(string $prefix, array $tags): array
    {
        $baseTags = [strtolower($prefix)];

        return array_unique(array_merge($baseTags, $tags));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \JsonException
     */
    private function serializeParams(array $params): string
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            sort($value);

            return $value;
        }

        if (is_object($value)) {
            return spl_object_hash($value);
        }

        return $value;
    }

    /**
     * Фабричный метод для создания ключа с USER_ID
     *
     * @param array<string, mixed> $params
     * @param string[]             $tags
     *
     * @throws \JsonException
     */
    public static function withUserId(
        string $prefix,
        int $userId,
        array $params = [],
        array $tags = [],
    ): self {
        $params['USER_ID'] = $userId;

        return new self($prefix, $params, $tags);
    }
}
