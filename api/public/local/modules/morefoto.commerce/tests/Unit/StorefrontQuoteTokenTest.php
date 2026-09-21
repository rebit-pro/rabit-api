<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class StorefrontQuoteTokenTest extends TestCase
{
    public function testExpiryBoundaryRejectsQuoteBeforeRecalculation(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('QUOTE_EXPIRED');
        $this->validator('2026-09-21 10:00:00')->execute(str_repeat('a', 64), str_repeat('b', 64), []);
    }

    public function testDifferentGalleryCannotUseAnotherQuote(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('QUOTE_STALE');
        $this->validator('2026-09-21 10:15:00')->execute(str_repeat('c', 64), str_repeat('b', 64), []);
    }

    public function testChangedFingerprintRejectsStillUnexpiredQuote(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('QUOTE_STALE');
        $this->validator('2026-09-21 10:15:00', true)->execute(str_repeat('a', 64), str_repeat('b', 64), []);
    }

    private function validator(string $expiresAt, bool $recalculate = false): ValidateQuoteUseCase
    {
        $repository = $this->createStub(QuoteRepository::class);
        $repository->method('find')->willReturn([
            'GALLERY_HASH' => hash('sha256', str_repeat('a', 64)),
            'FINGERPRINT' => 'old',
            'EXPIRES_AT' => $expiresAt,
        ]);
        $calculator = $this->createMock(StorefrontQuote::class);
        if ($recalculate) {
            $calculator->expects(self::once())->method('calculate')->willReturn(['quote' => [], 'fingerprint' => 'new']);
        } else {
            $calculator->expects(self::never())->method('calculate');
        }
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-21T10:00:00Z'));
        $transaction = $this->createStub(QuoteTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());

        return new ValidateQuoteUseCase($calculator, $repository, $clock, $transaction);
    }
}
