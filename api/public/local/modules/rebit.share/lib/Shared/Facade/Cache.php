<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Facade;

use Bitrix\Main\Application;
use Bitrix\Main\Data\ManagedCache;
use Bitrix\Main\SystemException;
use Bitrix\Main\Data\Cache as DataCache;
use Bitrix\Main\Data\TaggedCache;
use Rebit\Share\Shared\Enum\LogChannelEnum;

/**
 * Фасад для работы с кешем Битрикса.
 *
 * Автоматически клонирует не-readonly объекты при извлечении\записи в кеш для предотвращения случайной мутации
 * кешированных данных. После кеширования можно безопасно изменять объекты, кеш не изменится.
 *
 * Производительность: для оптимальной скорости реализуйте метод __clone() в DTO, которые кешируются.
 * Объекты без __clone() клонируются через Reflection, что в ~10 раз медленнее.
 *
 * Пример реализации __clone():
 *
 * ```
 * class UserDTO {
 *     public function __construct(
 *         public string $name,
 *         public AddressDTO $address,
 *         // @var int[] $tags
 *         public array $tags,
 *     ) {}
 *
 *     public function __clone(): void {
 *         $this->address = clone $this->address;
 *         // Примитивы и массивы примитивов клонировать не нужно, а массивы объектов обязательно.
 *     }
 * }
 * ```
 * ВНИМАНИЕ: если вы реализуете __clone, то делайте это и для всех вложенных объектов объекта,
 * т.к. они уже не будут проверяться этим фасадом.
 */
final class Cache
{
    private const int DEFAULT_TTL = 3600;

    private static ?ManagedCache $managedCache = null;

    private static ?TaggedCache $taggedCache = null;

    private static ?DataCache $dataCache = null;

    private static ?string $deployTimestamp = null;

    private static array $loggedClasses = []; // для логирования сообщений о классах без __clone

    // region Тестирование

    /**
     * Заменяет кеш-инстанс (например, в тестах можно подставить mock).
     */
    public static function setCache(?ManagedCache $cache): void
    {
        self::$managedCache = $cache;
    }

    public static function setTaggedCache(?TaggedCache $taggedCache): void
    {
        self::$taggedCache = $taggedCache;
    }

    public static function setDataCache(?DataCache $dataCache): void
    {
        self::$dataCache = $dataCache;
    }

    // endregion

    // region API

    /**
     * Возвращает результат $getDataCallback из кеша. При необходимости сохраняет его в кеш.
     *
     * Объекты автоматически клонируются при извлечении из кеша.
     *
     * Пример использования:
     *
     * ```
     * $favoriteIds = [1,2,3];
     * $products = Cache::remember(
     *      static function() use ($favoriteIds): array {
     *          $shortProducts = new \Catalog\ShortProducts($favoriteIds);
     *
     *          return $shortProducts->getProductData();
     *      },
     *      'products' . __METHOD__ . implode('', $favoriteIds)
     * );
     * ```
     *
     * @throws SystemException
     */
    public static function remember(
        callable $getDataCallback,
        string $cacheId,
        int $ttl = self::DEFAULT_TTL,
        bool $clearOnDeploy = false,
    ): mixed {
        if ('' === $cacheId) {
            throw new SystemException('Cache id must not be empty.');
        }

        if ($clearOnDeploy) {
            $cacheId .= self::getDeployTimestamp();
        }

        $cache = self::getManagedCache();

        if ($cache->read($ttl, $cacheId)) {
            return self::deepClone($cache->get($cacheId));
        }

        $data = $getDataCallback();
        $cache->set($cacheId, self::deepClone($data));

        return $data;
    }

    /**
     * Возвращает значение ключа кешированного ассоциативного массива. Если ключа нет, то добавляет его в массив.
     *
     * Очистка: forgetArrayDir($cacheId) — все ключи, forgetArrayValue($cacheId, $key) — конкретный.
     *
     * Значение автоматически клонируется при извлечении из кеша.
     *
     * Пример:
     *
     * ```
     * $productId = Cache::rememberArrayValue(
     *      static function (string $code): ?int {
     *          $entity = \Bitrix\Iblock\Iblock::wakeUp($iblockId)->getEntityDataClass();
     *          $row = $entity::query()
     *              ->setSelect(['ID'])
     *              ->where('CODE', $code)
     *              ->exec()
     *              ->fetch()
     *          ;
     *
     *          return null !== $row ? (int)$row['ID'] : null;
     *      },
     *      cacheId: 'product_code_to_id_map',
     *      key: $code,
     *      ttl: 3600,
     * );
     *```
     *
     * @throws SystemException
     */
    public static function rememberArrayValue(
        callable $getDataCallback,
        string $cacheId,  // по факту это $cacheDir
        int|string $key,
        int $ttl = self::DEFAULT_TTL,
        bool $clearOnDeploy = false,
    ): mixed {
        if ('' === $cacheId) {
            throw new SystemException('Cache id must not be empty.');
        }

        if ($clearOnDeploy) {
            $cacheId .= self::getDeployTimestamp();
        }

        $cache = self::getDataCache();
        $cache->noOutput();

        if (!$cache->startDataCache($ttl, (string)$key, $cacheId)) {
            return self::deepClone($cache->getVars());
        }

        try {
            $data = $getDataCallback($key);
        } catch (\Throwable $e) {
            $cache->abortDataCache();

            throw $e;
        }

        $cache->endDataCache(self::deepClone($data));

        return $data;
    }

    /**
     * Кэширует данные с поддержкой тегов для инвалидации.
     *
     * Объекты автоматически клонируются при извлечении из кеша.
     *
     * @param string[] $tags
     *
     * @throws SystemException
     */
    public static function rememberTagged(
        callable $getDataCallback,
        string $cacheId,
        string $cacheDir = '',
        array $tags = [],
        int $ttl = self::DEFAULT_TTL,
        bool $clearOnDeploy = false,
    ): mixed {
        if ('' === $cacheId) {
            throw new SystemException('Cache id must not be empty.');
        }

        if ($clearOnDeploy) {
            $cacheId .= self::getDeployTimestamp();
        }

        $cache = self::getDataCache();
        $cache->noOutput();

        if (!$cache->startDataCache($ttl, $cacheId, $cacheDir)) {
            return self::deepClone($cache->getVars());
        }

        if ([] !== $tags) {
            $taggedCache = self::getTaggedCache();
            $taggedCache->startTagCache($cacheDir);
            foreach ($tags as $tag) {
                $taggedCache->registerTag($tag);
            }
        }

        try {
            $data = $getDataCallback();
        } catch (\Throwable $e) {
            $cache->abortDataCache();

            throw $e;
        }

        $cache->endDataCache(self::deepClone($data));

        if ([] !== $tags) {
            $taggedCache->endTagCache();
        }

        return $data;
    }

    /**
     * Обновляет значения в тегированном кэше (для накопительного кэша).
     *
     * @param string[] $tags
     */
    public static function updateTagged(
        string $cacheId,
        string $cacheDir,
        array $newData,
        array $tags = [],
        int $ttl = self::DEFAULT_TTL,
    ): array {
        $cache = self::getDataCache();
        // Не кешируем вывод
        $cache->noOutput();

        $existingData = [];

        // Читаем существующий кэш
        if ($cache->initCache($ttl, $cacheId, $cacheDir)) {
            $existingData = (array)$cache->getVars();
        }

        $mergedData = array_merge($existingData, $newData);

        $cache->forceRewriting(true);
        $cache->initCache($ttl, $cacheId, $cacheDir);
        $cache->startDataCache();

        if ([] !== $tags) {
            $taggedCache = self::getTaggedCache();
            $taggedCache->startTagCache($cacheDir);
            foreach ($tags as $tag) {
                $taggedCache->registerTag($tag);
            }
        }

        $cache->endDataCache(self::deepClone($mergedData));

        if ([] !== $tags) {
            $taggedCache->endTagCache();
        }

        return self::deepClone($mergedData);
    }

    // endregion

    // region Очистка

    /**
     * Удаляет кеш, сохранённый через remember().
     */
    public static function forgetRemember(string $cacheId): void
    {
        self::getManagedCache()->clean($cacheId);
    }

    /**
     * Удаляет конкретный ключ, сохранённый через rememberArrayValue().
     */
    public static function forgetArrayValue(string $cacheId, int|string $key): void
    {
        self::getDataCache()->clean((string)$key, $cacheId);
    }

    /**
     * Удаляет все ключи директории, сохранённые через rememberArrayValue().
     */
    public static function forgetArrayDir(string $cacheId): void
    {
        self::getDataCache()->cleanDir($cacheId);
    }

    /**
     * Инвалидирует все записи, привязанные к тегу через rememberTagged().
     * ВНИМАНИЕ: удалятся все записи, которые находятся в одной директории с этим тегом.
     */
    public static function forgetTagged(string $tag): void
    {
        self::getTaggedCache()->clearByTag($tag);
    }

    // endregion

    // region Deep Clone

    /**
     * Выполняет глубокое клонирование для отвязки объектов от переданных в кеш данных.
     * Убирает сайд-эффект, что если изменить объекты после взятия из кеша битрикса, то они изменятся и в кеше.
     *
     * Использует __clone() если он реализован в объекте для максимальной производительности.
     * Иначе использует Reflection для настоящего deep clone.
     * Рекурсивно клонирует массивы и вложенные структуры.
     */
    private static function deepClone(mixed $data): mixed
    {
        if (is_object($data)) {
            if ($data instanceof \UnitEnum) {
                return $data;
            }

            $reflection = new \ReflectionClass($data);

            // Для readonly классов не нужен clone
            if ($reflection->isReadOnly()) {
                return $data;
            }

            // Если есть __clone - используем его (быстрый путь)
            if ($reflection->hasMethod('__clone')) {
                return clone $data;
            }

            if (!isset(self::$loggedClasses[$data::class])) {
                self::$loggedClasses[$data::class] = true;

                Log::channel(LogChannelEnum::todo)->warning('Кеширование не readonly объекта без метода __clone!', [
                    'class' => $data::class,
                ]);
            }

            // Иначе делаем медленный deep clone через Reflection (~8-13 раз медленнее)
            return self::reflectionDeepClone($data, $reflection);
        }

        if (is_array($data)) {
            $cloned = [];
            foreach ($data as $key => $value) {
                $cloned[$key] = self::deepClone($value);
            }

            return $cloned;
        }

        return $data;
    }

    /**
     * Выполняет deep clone через Reflection для объектов без __clone.
     */
    private static function reflectionDeepClone(object $object, \ReflectionClass $reflection): object
    {
        $newObject = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);
            $property->setValue($newObject, self::deepClone($value));
        }

        return $newObject;
    }

    // endregion

    // region Инстансы

    private static function getManagedCache(): ManagedCache
    {
        return self::$managedCache ??= Application::getInstance()->getManagedCache();
    }

    private static function getTaggedCache(): TaggedCache
    {
        return self::$taggedCache ??= Application::getInstance()->getTaggedCache();
    }

    private static function getDataCache(): DataCache
    {
        return self::$dataCache ?? DataCache::createInstance();
    }

    // endregion

    // region Сервисные методы
    private static function getDeployTimestamp(): string
    {
        if (null !== self::$deployTimestamp) {
            return self::$deployTimestamp;
        }

        $filePath = Application::getDocumentRoot() . '/.git/refs/heads/master';
        $mtime = @filemtime($filePath);

        return self::$deployTimestamp = false !== $mtime ? '_' . $mtime : '';
    }

    // endregion
}
