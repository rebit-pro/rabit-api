<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Bitrix\Main\DI\ServiceLocator;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerBusConfigBuilder
{
    /**
     * @param list<MessengerRouteDto> $routes
     */
    public static function build(ServiceLocator $locator, array $routes): MessengerBusConfigDto
    {
        /** @var array<class-string, list<callable>> $handlers */
        $handlers = [];
        /** @var array<class-string, list<string>> $routing */
        $routing = [];
        /** @var array<string, TransportInterface> $transports */
        $transports = [];

        foreach ($routes as $route) {
            $handlers[$route->messageClass][]
                = static fn(object $message): mixed => $locator->get($route->handlerClass)($message);
            $routing[$route->messageClass][] = $route->queue->value;

            if (!isset($transports[$route->queue->value])) {
                /** @var TransportInterface $transport */
                $transport = $locator->get($route->queue->transportKey());
                $transports[$route->queue->value] = $transport;
            }
        }

        return new MessengerBusConfigDto(
            handlers: $handlers,
            routing: $routing,
            transports: $transports,
        );
    }
}
