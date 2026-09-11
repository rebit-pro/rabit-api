<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Infrastructure\Lead;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Notification\Infrastructure\Lead\FallbackLeadNotifier;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class FallbackLeadNotifierTest extends TestCase
{
    public function testPrimarySuccessSkipsFallback(): void
    {
        $primary = $this->createMock(LeadNotifierInterface::class);
        $primary->expects(self::once())->method('notify');

        $fallback = $this->createMock(LeadNotifierInterface::class);
        $fallback->expects(self::never())->method('notify');

        $this->composite($primary, $fallback)->notify($this->lead());
    }

    public function testPrimaryFailureDeliversViaFallback(): void
    {
        $fallback = $this->createMock(LeadNotifierInterface::class);
        $fallback->expects(self::once())->method('notify');

        $this->composite($this->failingNotifier(), $fallback)->notify($this->lead());
    }

    public function testBothChannelsFailedRethrowsFallbackError(): void
    {
        $composite = $this->composite($this->failingNotifier(), $this->failingNotifier());

        $this->expectException(HttpException::class);

        $composite->notify($this->lead());
    }

    private function composite(LeadNotifierInterface $primary, LeadNotifierInterface $fallback): FallbackLeadNotifier
    {
        return new FallbackLeadNotifier(new NullLogger(), $primary, $fallback);
    }

    private function failingNotifier(): LeadNotifierInterface
    {
        $notifier = $this->createStub(LeadNotifierInterface::class);
        $notifier->method('notify')->willThrowException(new HttpException('Не удалось отправить заявку', 502));

        return $notifier;
    }

    private function lead(): LeadMessageDto
    {
        return new LeadMessageDto(
            name: 'Иван',
            phone: '+7 900 000-00-00',
            description: 'Нужен сайт на Битриксе',
            page: 'https://rebit-pro.ru/',
            email: 'ivan@example.com',
        );
    }
}
