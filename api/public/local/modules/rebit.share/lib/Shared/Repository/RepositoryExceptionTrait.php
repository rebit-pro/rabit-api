<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Repository;

use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\SystemException;
use Rebit\Share\Shared\Exception\RepositoryException;

/**
 * Trait для перехвата Bitrix ORM исключений в репозиториях.
 *
 * SystemException — базовый для ArgumentException и ObjectPropertyException,
 * поэтому одного catch достаточно для всех ORM-ошибок.
 */
trait RepositoryExceptionTrait
{
    /**
     * Обёртка для запросов на чтение. Перехватывает SystemException → RepositoryException.
     *
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     *
     * @throws RepositoryException
     */
    protected function query(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (SystemException $e) {
            throw new RepositoryException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Сохранение EntityObject. Перехватывает ORM-ошибки и проверяет Result.
     *
     * @throws RepositoryException
     */
    protected function persist(EntityObject $entity): void
    {
        try {
            $result = $entity->save();
        } catch (SystemException $e) {
            throw new RepositoryException($e->getMessage(), 0, $e);
        }

        if (!$result->isSuccess()) {
            throw new RepositoryException(
                implode('; ', $result->getErrorMessages()),
            );
        }
    }
}
