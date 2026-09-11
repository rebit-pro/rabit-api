<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * Фабрика для ручной сборки Symfony Messenger MessageBus без Symfony Framework.
 */
final class MessengerBusFactory
{
    public static function create(MessengerBusConfigDto $config): MessageBusInterface
    {
        $sendersLocator = new SendersLocator(
            $config->routing,
            new SimpleServiceContainer($config->transports),
        );
        $handlersLocator = new HandlersLocator($config->handlers);

        return new MessageBus([
            new SendMessageMiddleware($sendersLocator),
            new HandleMessageMiddleware($handlersLocator),
        ]);
    }
}
