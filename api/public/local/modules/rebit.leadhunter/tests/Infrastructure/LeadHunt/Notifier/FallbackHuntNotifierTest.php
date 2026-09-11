<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Tests\Infrastructure\LeadHunt\Notifier;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier\FallbackHuntNotifier;

/**
 * @internal
 */
final class FallbackHuntNotifierTest extends TestCase
{
    public function testPrimarySuccessSkipsFallback(): void
    {
        $primary = $this->notifier(true);
        $fallback = $this->createMock(HuntNotifierInterface::class);
        $fallback->expects(self::never())->method('notify');

        self::assertTrue($this->composite($primary, $fallback)->notify($this->lead()));
    }

    public function testPrimaryFailureDeliversViaFallbackImmediately(): void
    {
        $primary = $this->notifier(false);
        $fallback = $this->createMock(HuntNotifierInterface::class);
        $fallback->expects(self::once())->method('notify')->willReturn(true);

        self::assertTrue($this->composite($primary, $fallback)->notify($this->lead()));
    }

    public function testBothChannelsFailedReturnsFalse(): void
    {
        $primary = $this->notifier(false);
        $fallback = $this->notifier(false);

        self::assertFalse($this->composite($primary, $fallback)->notify($this->lead()));
    }

    private function composite(HuntNotifierInterface $primary, HuntNotifierInterface $fallback): FallbackHuntNotifier
    {
        return new FallbackHuntNotifier(new NullLogger(), $primary, $fallback);
    }

    private function notifier(bool $result): HuntNotifierInterface
    {
        $notifier = $this->createStub(HuntNotifierInterface::class);
        $notifier->method('notify')->willReturn($result);

        return $notifier;
    }

    private function lead(int $attempts = 0): PendingLeadDto
    {
        return new PendingLeadDto(
            id: 1,
            source: LeadSourceEnum::FL_RU,
            title: 'Правки Битрикс',
            description: 'Мелкие правки',
            url: 'https://www.fl.ru/projects/1/pravki.html',
            matchedKeywords: ['битрикс'],
            attempts: $attempts,
        );
    }
}
