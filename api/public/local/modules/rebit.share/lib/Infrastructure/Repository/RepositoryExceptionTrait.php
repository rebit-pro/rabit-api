<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Repository;

use Rebit\Share\Shared\Repository\RepositoryExceptionTrait as SharedRepositoryExceptionTrait;

/**
 * Trait для перехвата Bitrix ORM исключений в репозиториях.
 *
 * SystemException — базовый для ArgumentException и ObjectPropertyException,
 * поэтому одного catch достаточно для всех ORM-ошибок.
 */
trait RepositoryExceptionTrait
{
    use SharedRepositoryExceptionTrait;
}
