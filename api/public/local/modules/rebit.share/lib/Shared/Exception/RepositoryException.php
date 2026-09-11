<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Exception;

/**
 * Исключение при ошибках в репозиториях (запросы, сохранение).
 * Оборачивает Bitrix ORM исключения (SystemException, ArgumentException, ObjectPropertyException).
 */
final class RepositoryException extends RebitException {}
