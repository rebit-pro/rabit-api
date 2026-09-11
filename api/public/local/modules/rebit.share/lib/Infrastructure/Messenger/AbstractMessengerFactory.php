<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class AbstractMessengerFactory
{
    public static function createBus(ServiceLocator $locator): MessageBusInterface
    {
        return MessengerBusFactory::create(
            MessengerBusConfigBuilder::build($locator, static::routes()),
        );
    }

    public static function createPublisher(ServiceLocator $locator): MessagePublisherInterface
    {
        return new MessengerMessagePublisher(
            static::createBus($locator),
            $locator->get(DedupCacheInterface::class),
        );
    }

    /**
     * @return list<MessengerRouteDto>
     */
    abstract protected static function routes(): array;
}
