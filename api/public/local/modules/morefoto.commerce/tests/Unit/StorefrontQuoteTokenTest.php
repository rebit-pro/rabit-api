<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Storefront\Exception\QuotePriceChangedException;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class StorefrontQuoteTokenTest extends TestCase
{
    private const array QUOTE = [
        'lines' => [['id' => 'a:p', 'quantity' => 1, 'unitPrice' => 25000, 'total' => 25000, 'discount' => 0, 'coveredByGift' => false]],
        'total' => 25000, 'subtotal' => 25000, 'discount' => 0, 'giftSaving' => 0, 'gifts' => [],
    ];

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

    public function testChangedFingerprintWithSameMoneyIsStale(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('QUOTE_STALE');
        $this->validator('2026-09-21 10:15:00', 'new', self::QUOTE)->execute(str_repeat('a', 64), str_repeat('b', 64), []);
    }

    public function testChangedMoneyIsReportedSeparately(): void
    {
        $this->expectException(QuotePriceChangedException::class);
        $quote = self::QUOTE;
        $quote['total'] = 12500;
        $quote['lines'][0]['total'] = 12500;
        $this->validator('2026-09-21 10:15:00', 'new', $quote)->execute(str_repeat('a', 64), str_repeat('b', 64), []);
    }

    public function testCurrentQuoteReturnsValidatedGalleryContext(): void
    {
        $result = $this->validator('2026-09-21 10:15:00', 'old', self::QUOTE)->execute(str_repeat('a', 64), str_repeat('b', 64), []);

        self::assertSame(str_repeat('b', 64), $result->quote->quoteToken);
        self::assertSame('group-id', $result->gallery->group->publicId);
        self::assertSame(25000, $result->quote->quote['total']);
    }

    /** @param null|array<string, mixed> $recalculated */
    private function validator(string $expiresAt, ?string $fingerprint = null, ?array $recalculated = null): ValidateQuoteUseCase
    {
        $repository = $this->createStub(QuoteRepository::class);
        $repository->method('find')->willReturn([
            'GALLERY_HASH' => hash('sha256', str_repeat('a', 64)),
            'FINGERPRINT' => 'old',
            'EXPIRES_AT' => $expiresAt,
            'SNAPSHOT_JSON' => json_encode(self::QUOTE, JSON_THROW_ON_ERROR),
        ]);
        $calculator = $this->createMock(StorefrontQuote::class);
        if (null !== $fingerprint) {
            $group = new GalleryGroupOutputDto(1, 2, 'group-id', 'Institution', 'Shoot', 'Group', 'regular', null, null, 1);
            $calculator->expects(self::once())->method('calculate')->willReturn([
                'quote' => $recalculated, 'fingerprint' => $fingerprint,
                'gallery' => new GalleryAccessOutputDto($group, 'open', '2026-09-21T10:00:00+00:00', 1, []),
            ]);
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
