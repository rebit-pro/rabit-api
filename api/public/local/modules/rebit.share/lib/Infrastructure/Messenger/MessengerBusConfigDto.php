<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Конфигурация для ручной сборки Symfony Messenger MessageBus.
 */
final readonly class MessengerBusConfigDto
{
    /**
     * @param array<class-string, list<callable>> $handlers   [MessageClass => [callable, ...]]
     * @param array<class-string, list<string>>   $routing    [MessageClass => ['transport_name', ...]]
     * @param array<string, TransportInterface>   $transports ['transport_name' => TransportInterface]
     */
    public function __construct(
        public array $handlers,
        public array $routing,
        public array $transports,
    ) {}
}
