<?php

declare(strict_types=1);

/**
 * Рабочие стабы Bitrix-классов для PHPUnit-тестов.
 * Подключаются в tests/bootstrap.php.
 */

namespace Bitrix\Main\Type;

if (!class_exists(Date::class)) {
    class Date
    {
        protected int $timestamp;

        public function __construct(string $date = '', string $format = '')
        {
            $this->timestamp = ('' !== $date) ? (int)strtotime($date) : time();
        }

        public function format(string $format): string
        {
            return date($format, $this->timestamp);
        }

        public static function createFromTimestamp(int $timestamp): static
        {
            $instance = new static();
            $instance->timestamp = $timestamp;

            return $instance;
        }

        public function toString(mixed $culture = null): string
        {
            return date('d.m.Y', $this->timestamp);
        }

        public function add(string $interval): static
        {
            $timestamp = strtotime($interval, $this->timestamp);

            if (false !== $timestamp) {
                $this->timestamp = $timestamp;
            }

            return $this;
        }

        public function getTimestamp(): int
        {
            return $this->timestamp;
        }
    }
}

if (!class_exists(DateTime::class)) {
    class DateTime extends Date
    {
        public static function createFromTimestamp(int $timestamp): static
        {
            $instance = new static();
            $instance->timestamp = $timestamp;

            return $instance;
        }

        public function toString(mixed $culture = null): string
        {
            return date('d.m.Y H:i:s', $this->timestamp);
        }
    }
}

namespace Bitrix\Main\Web;

if (!class_exists(Json::class)) {
    class Json
    {
        public static function encode(mixed $data): string
        {
            return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        public static function decode(string $data): mixed
        {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }
    }
}

/**
 * Стабы Bitrix ORM — нужны, т.к. EO_*-классы генерируются только в рантайме Bitrix.
 */

namespace Bitrix\Main;

if (!class_exists(Result::class)) {
    class Result
    {
        public function isSuccess(): bool
        {
            return true;
        }

        /** @return array<string> */
        public function getErrorMessages(): array
        {
            return [];
        }
    }
}

if (!class_exists(SystemException::class)) {
    class SystemException extends \RuntimeException {}
}

if (!class_exists(Application::class)) {
    class Application
    {
        private static ?self $instance = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public static function getDocumentRoot(): string
        {
            return sys_get_temp_dir();
        }

        public function getTaggedCache(): Data\TaggedCache
        {
            return new Data\TaggedCache();
        }

        public function getManagedCache(): Data\ManagedCache
        {
            return new Data\ManagedCache();
        }
    }
}

namespace Bitrix\Main\Data;

if (!class_exists(Cache::class)) {
    /**
     * Стаб DataCache — в тестах всегда кэш-промах, callback выполняется напрямую.
     */
    class Cache
    {
        public static function createInstance(): self
        {
            return new self();
        }

        public function noOutput(): void {}

        public function startDataCache(int $ttl = 0, string $key = '', string $dir = ''): bool
        {
            return true;
        }

        public function endDataCache(mixed $vars = null): void {}

        public function abortDataCache(): void {}

        public function getVars(): mixed
        {
            return null;
        }
    }
}

if (!class_exists(ManagedCache::class)) {
    class ManagedCache
    {
        public function read(int $ttl, string $uniqueId, string $tableId = ''): bool
        {
            return false;
        }

        public function get(string $uniqueId): mixed
        {
            return null;
        }

        public function set(string $uniqueId, mixed $val): void {}

        public function clean(string $uniqueId, string $tableId = ''): void {}
    }
}

if (!class_exists(TaggedCache::class)) {
    class TaggedCache {}
}

namespace Bitrix\Main\Config;

if (!class_exists(Configuration::class)) {
    class Configuration
    {
        private static ?self $instance = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public function get(string $name): mixed
        {
            return null;
        }
    }
}

namespace Bitrix\Main\ORM\Data;

if (!class_exists(Result::class)) {
    class Result extends \Bitrix\Main\Result {}
}

namespace Bitrix\Main\ORM\Query;

if (!class_exists(Result::class)) {
    class Result
    {
        public function fetch(): array|false
        {
            return false;
        }
    }
}
