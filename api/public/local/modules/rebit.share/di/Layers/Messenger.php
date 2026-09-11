<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Infrastructure\Messenger\BitrixDedupCache;
use Rebit\Share\Infrastructure\Messenger\ConsumerRunner;
use Rebit\Share\Infrastructure\Messenger\ConsumerRunnerInterface;
use Rebit\Share\Infrastructure\Messenger\DedupCacheInterface;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    AmqpConnectionFactory::class => [
        'className' => AmqpConnectionFactory::class,
        'constructorParams' => static fn(): array => [
            (string)getenv('MESSENGER_TRANSPORT_DSN'),
        ],
    ],
    MessageTransportFactoryInterface::class => [
        'constructor' => static fn(): MessageTransportFactoryInterface => ServiceLocator::getInstance()
            ->get(AmqpConnectionFactory::class),
    ],
    ConsumerRunnerInterface::class => [
        'constructor' => static function(): ConsumerRunnerInterface {
            $locator = ServiceLocator::getInstance();

            return new ConsumerRunner(
                Log::channel(LogChannelEnum::cli),
                new MultiplierRetryStrategy(maxRetries: 3, delayMilliseconds: 1000, multiplier: 2),
                $locator->get(AmqpConnectionFactory::class)->create(MessengerQueueEnum::FAILED),
            );
        },
    ],
    MessageConsumerRunnerInterface::class => [
        'constructor' => static fn(): MessageConsumerRunnerInterface => ServiceLocator::getInstance()
            ->get(ConsumerRunnerInterface::class),
    ],
    DedupCacheInterface::class => [
        'className' => BitrixDedupCache::class,
    ],
    MessengerQueueEnum::AUDIT->transportKey() => [
        'constructor' => static fn(): TransportInterface => ServiceLocator::getInstance()
            ->get(AmqpConnectionFactory::class)
            ->create(MessengerQueueEnum::AUDIT),
    ],
];
