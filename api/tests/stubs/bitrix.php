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

        /** Тесты SQL-репозиториев подменяют соединение через reflection; по умолчанию запросы ничего не возвращают. */
        private static ?DB\Connection $connection = null;

        /** @var array<string, string>|null параметры текущего маршрута для тестов request-мапперов; null — маршрута нет */
        public static ?array $routeParameters = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public static function getConnection(): DB\Connection
        {
            return self::$connection ??= new DB\Connection();
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

        public function hasCurrentRoute(): bool
        {
            return null !== self::$routeParameters;
        }

        public function getCurrentRoute(): Routing\Route
        {
            return new Routing\Route(self::$routeParameters ?? []);
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

namespace Bitrix\Main\DB;

if (!class_exists(Result::class)) {
    /** Стаб результата SQL-запроса: тесты подставляют строки через моки `fetch()`. */
    class Result
    {
        public function fetch(): array|false
        {
            return false;
        }
    }
}

if (!class_exists(SqlHelper::class)) {
    class SqlHelper
    {
        public function forSql(string $value): string
        {
            return addslashes($value);
        }
    }
}

if (!class_exists(Connection::class)) {
    /** Стаб соединения: тесты наследуют его, чтобы записывать SQL и подставлять строки результата. */
    class Connection
    {
        public function query(string $sql): Result
        {
            return new Result();
        }

        public function queryExecute(string $sql): void {}

        public function getSqlHelper(): SqlHelper
        {
            return new SqlHelper();
        }

        public function getAffectedRowsCount(): int
        {
            return 0;
        }

        public function getInsertedId(): int
        {
            return 0;
        }
    }
}

namespace Bitrix\Main;

if (!class_exists(Response::class)) {
    /** Стаб ответа: хранит тело, достаточно для проверки JSON-ответов контроллеров. */
    class Response
    {
        protected $content = '';

        public function setContent($content)
        {
            $this->content = $content;

            return $this;
        }

        public function getContent()
        {
            return $this->content;
        }
    }
}

if (!class_exists(HttpResponse::class)) {
    class HttpResponse extends Response
    {
        private $status = 200;

        /** @var array<string, string> */
        private array $headers = [];

        public function setStatus($status)
        {
            $this->status = $status;

            return $this;
        }

        public function getStatus()
        {
            return $this->status;
        }

        public function addHeader($name, $value = '')
        {
            $this->headers[$name] = $value;

            return $this;
        }

        /** @return array<string, string> */
        public function getHeaders(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists(HttpRequest::class)) {
    /** Стаб HTTP-запроса: тесты подставляют заголовки, метод и multipart-списки через моки. */
    class HttpRequest
    {
        public function getHeader($name)
        {
            return null;
        }

        public function getRequestMethod()
        {
            return 'GET';
        }

        public function getPostList()
        {
            return new Type\ParameterDictionary();
        }

        public function getFileList()
        {
            return new Type\ParameterDictionary();
        }
    }
}

namespace Bitrix\Main\Type;

if (!class_exists(ParameterDictionary::class)) {
    /** Стаб словаря параметров запроса. */
    class ParameterDictionary
    {
        /** @param array<array-key, mixed> $values */
        public function __construct(
            private readonly array $values = [],
        ) {}

        /** @return array<array-key, mixed> */
        public function getValues(): array
        {
            return $this->values;
        }
    }
}

namespace Bitrix\Main\Routing;

if (!class_exists(Route::class)) {
    /** Стаб маршрута: значения параметров передаются в конструктор. */
    class Route
    {
        /** @param array<string, string> $parameters */
        public function __construct(
            private readonly array $parameters = [],
        ) {}

        public function getParameterValue($name)
        {
            return $this->parameters[$name] ?? null;
        }
    }
}

namespace Bitrix\Main;

if (!class_exists(EventResult::class)) {
    class EventResult {}
}

if (!class_exists(Event::class)) {
    /** Стаб события Bitrix: параметры передаются в конструктор. */
    class Event
    {
        /** @param array<string, mixed> $parameters */
        public function __construct(
            private readonly array $parameters = [],
        ) {}

        public function getParameter($key)
        {
            return $this->parameters[$key] ?? null;
        }
    }
}

namespace Bitrix\Main\Engine\Response;

if (!class_exists(Json::class)) {
    /** Стаб JSON-ответа Bitrix: наследник переопределяет `setData()` своим сериализатором. */
    class Json extends \Bitrix\Main\HttpResponse
    {
        protected $data;
        protected $jsonEncodingOptions = 0;

        public function __construct($data = null, $options = 0)
        {
            $this->jsonEncodingOptions = $options;
            $this->setData($data);
        }

        public function setData($data)
        {
            $this->data = json_encode($data, $this->jsonEncodingOptions | JSON_THROW_ON_ERROR);

            return $this->setContent($this->data);
        }
    }
}

namespace Bitrix\Main\Engine\ActionFilter;

if (!class_exists(Base::class)) {
    class Base
    {
        public function __construct() {}
    }
}
