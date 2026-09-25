<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentAttemptUseCase;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentQuoteUseCase;
use Morefoto\Payment\Application\Payment\UseCase\StartPaymentAttemptUseCase;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Собирает сценарии оплаты на фейках: один заказ, провайдер с очередью ответов, управляемое время. */
final class PaymentScenario
{
    public InMemoryAttempts $attempts;
    public InMemoryFacts $facts;
    public FakeOrders $orders;
    public FakeProvider $provider;
    public \DateTimeImmutable $now;
    public PaymentAttemptPolicy $policy;
    private int $uuid = 0;

    /** @param list<PaymentMethodEnum> $methods */
    public function __construct(public array $methods = [PaymentMethodEnum::SBP, PaymentMethodEnum::BANK_CARD], public bool $enabled = true, ?FakeOrders $orders = null)
    {
        $this->attempts = new InMemoryAttempts();
        $this->facts = new InMemoryFacts();
        $this->orders = $orders ?? new FakeOrders();
        $this->provider = new FakeProvider();
        $this->now = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
        $this->policy = new PaymentAttemptPolicy();
    }

    public function settings(): PaymentSettings
    {
        return new PaymentSettings($this->enabled, $this->methods, 'https://app.example.test/');
    }

    public function clock(): ClockInterface
    {
        return new readonly class($this) implements ClockInterface {
            public function __construct(private PaymentScenario $scenario) {}

            public function now(): \DateTimeImmutable
            {
                return $this->scenario->now;
            }
        };
    }

    public function transaction(): PaymentTransactionInterface
    {
        return new readonly class implements PaymentTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
    }

    public function ids(): PaymentIdGeneratorInterface
    {
        return new class($this) implements PaymentIdGeneratorInterface {
            public function __construct(private PaymentScenario $scenario) {}

            public function uuid(): string
            {
                return $this->scenario->nextUuid();
            }
        };
    }

    public function nextUuid(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', ++$this->uuid);
    }

    public function reconciler(): PaymentReconciler
    {
        return new PaymentReconciler($this->attempts, $this->facts, $this->provider, $this->orders, $this->transaction(), $this->policy, $this->settings(), $this->clock(), new NullLogger());
    }

    public function start(): StartPaymentAttemptUseCase
    {
        return new StartPaymentAttemptUseCase(
            $this->orders,
            $this->attempts,
            $this->reconciler(),
            $this->provider,
            $this->transaction(),
            $this->policy,
            new PaymentQuoteToken(),
            $this->settings(),
            $this->ids(),
            new PaymentOutputMapper(),
            $this->clock(),
        );
    }

    public function quote(): GetPaymentQuoteUseCase
    {
        return new GetPaymentQuoteUseCase($this->orders, $this->attempts, $this->policy, new PaymentQuoteToken(), $this->settings(), $this->clock());
    }

    public function attempt(): GetPaymentAttemptUseCase
    {
        return new GetPaymentAttemptUseCase($this->orders, $this->attempts, $this->reconciler(), $this->policy, new PaymentOutputMapper(), $this->clock());
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify('+' . $seconds . ' seconds');
    }
}
