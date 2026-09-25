<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Order\Contract\OrderTransactionInterface;
use Morefoto\Commerce\Application\Order\Dto\CreatedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerOutputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\PlacedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Service\CheckoutAvailability;
use Morefoto\Commerce\Application\Order\Service\CheckoutReceipts;
use Morefoto\Commerce\Application\Order\Service\OrderPlacement;
use Morefoto\Commerce\Application\Order\UseCase\CreateOrderUseCase;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Dto\ValidatedQuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Order\Service\BuyerPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Storefront\Exception\QuotePriceChangedException;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryContextOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderCheckoutUseCaseTest extends TestCase
{
    public function testDisabledCheckoutTouchesNothing(): void
    {
        $gallery = $this->createMock(GalleryAccessInterface::class);
        $gallery->expects(self::never())->method('context');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('PURCHASE_DISABLED');
        $this->useCase(enabled: false, gallery: $gallery)->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input());
    }

    public function testPreparingGalleryCannotOrder(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GALLERY_NOT_READY');
        $this->useCase(state: 'preparing')->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input());
    }

    public function testClosedGroupRejectsNewOrderBeforeQuoteValidation(): void
    {
        $receipts = $this->createStub(CheckoutReceipts::class);
        $receipts->method('reserve')->willReturn(null);
        $quotes = $this->createMock(ValidateQuoteUseCase::class);
        $quotes->expects(self::never())->method('executeWithinTransaction');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GALLERY_CLOSED');
        $this->useCase(state: 'closed', receipts: $receipts, quotes: $quotes)->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input());
    }

    public function testReplayReturnsOriginalOrderBeforeRevalidatingQuoteOrGroup(): void
    {
        $original = $this->created('original-key');
        $receipts = $this->createMock(CheckoutReceipts::class);
        $receipts->expects(self::once())->method('reserve')->willReturn($original);
        $receipts->expects(self::never())->method('complete');
        $quotes = $this->createMock(ValidateQuoteUseCase::class);
        $quotes->expects(self::never())->method('executeWithinTransaction');

        $result = $this->useCase(state: 'closed', receipts: $receipts, quotes: $quotes)->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input());

        self::assertSame($original, $result);
    }

    public function testNewOrderIsPlacedAndReceiptCompletedInsideOneTransaction(): void
    {
        $created = $this->created('new-key');
        $receipts = $this->createMock(CheckoutReceipts::class);
        $receipts->expects(self::once())->method('reserve')->willReturn(null);
        $receipts->expects(self::once())->method('complete');
        $quotes = $this->createMock(ValidateQuoteUseCase::class);
        $quotes->expects(self::once())->method('executeWithinTransaction')->with(str_repeat('a', 64), str_repeat('c', 64))->willReturn($this->validated());
        $placement = $this->createMock(OrderPlacement::class);
        $placement->expects(self::once())->method('place')->willReturn(new PlacedOrderOutputDto(7, $created));
        $transaction = $this->createMock(OrderTransactionInterface::class);
        $transaction->expects(self::once())->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());

        $result = $this->useCase(receipts: $receipts, quotes: $quotes, placement: $placement, transaction: $transaction)
            ->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input())
        ;

        self::assertSame($created, $result);
    }

    public function testAcceptedDocumentsAreRecordedForThePlacedOrder(): void
    {
        $receipts = $this->createStub(CheckoutReceipts::class);
        $receipts->method('reserve')->willReturn(null);
        $quotes = $this->createStub(ValidateQuoteUseCase::class);
        $quotes->method('executeWithinTransaction')->willReturn($this->validated());
        $placement = $this->createStub(OrderPlacement::class);
        $placement->method('place')->willReturn(new PlacedOrderOutputDto(7, $this->created('new-key')));
        $consents = $this->createMock(ConsentRecorderInterface::class);
        $consents->expects(self::once())->method('record')->with(ConsentContextEnum::ORDER, 7, $this->input()->consents);

        $this->useCase(receipts: $receipts, quotes: $quotes, placement: $placement, consents: $consents)
            ->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input())
        ;
    }

    public function testMissingConsentFailsTheCheckoutBeforeTheReceiptCompletes(): void
    {
        $receipts = $this->createMock(CheckoutReceipts::class);
        $receipts->method('reserve')->willReturn(null);
        $receipts->expects(self::never())->method('complete');
        $quotes = $this->createStub(ValidateQuoteUseCase::class);
        $quotes->method('executeWithinTransaction')->willReturn($this->validated());
        $placement = $this->createStub(OrderPlacement::class);
        $placement->method('place')->willReturn(new PlacedOrderOutputDto(7, $this->created('new-key')));
        $consents = $this->createStub(ConsentRecorderInterface::class);
        $consents->method('record')->willThrowException(new HttpException('CONSENT_REQUIRED', 422));
        $this->expectExceptionMessage('CONSENT_REQUIRED');

        $this->useCase(receipts: $receipts, quotes: $quotes, placement: $placement, consents: $consents)
            ->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input())
        ;
    }

    public function testChangedPriceReturnsFreshQuoteForConfirmation(): void
    {
        $receipts = $this->createStub(CheckoutReceipts::class);
        $receipts->method('reserve')->willReturn(null);
        $quotes = $this->createStub(ValidateQuoteUseCase::class);
        $quotes->method('executeWithinTransaction')->willThrowException(new QuotePriceChangedException());
        $fresh = $this->createMock(CreateQuoteUseCase::class);
        $fresh->expects(self::once())->method('execute')->willReturn(new QuoteOutputDto($this->quote(12500), str_repeat('d', 64), '2026-09-22T12:45:00+00:00'));
        try {
            $this->useCase(receipts: $receipts, quotes: $quotes, freshQuotes: $fresh)->execute(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input());
            self::fail('PRICE_CHANGED expected.');
        } catch (HttpException $error) {
            self::assertSame('PRICE_CHANGED', $error->getMessage());
            self::assertSame(409, $error->getCode());
            self::assertSame(str_repeat('d', 64), $error->getDetails()['quoteToken']);
            self::assertSame(12500, $error->getDetails()['quote']['total']);
        }
    }

    private function useCase(
        bool $enabled = true,
        string $state = 'open',
        ?GalleryAccessInterface $gallery = null,
        ?CheckoutReceipts $receipts = null,
        ?ValidateQuoteUseCase $quotes = null,
        ?CreateQuoteUseCase $freshQuotes = null,
        ?OrderPlacement $placement = null,
        ?OrderTransactionInterface $transaction = null,
        ?ConsentRecorderInterface $consents = null,
    ): CreateOrderUseCase {
        if (null === $gallery) {
            $gallery = $this->createStub(GalleryAccessInterface::class);
            $gallery->method('context')->willReturn(new GalleryContextOutputDto($this->group(), $state, '2026-09-22T10:00:00+00:00', 1));
        }
        if (null === $transaction) {
            $transaction = $this->createStub(OrderTransactionInterface::class);
            $transaction->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());
        }

        return new CreateOrderUseCase(
            new CheckoutAvailability($enabled),
            $gallery,
            $receipts ?? $this->createStub(CheckoutReceipts::class),
            new BuyerPolicy(),
            $quotes ?? $this->createStub(ValidateQuoteUseCase::class),
            $freshQuotes ?? $this->createStub(CreateQuoteUseCase::class),
            $placement ?? $this->createStub(OrderPlacement::class),
            $transaction,
            $consents ?? $this->createStub(ConsentRecorderInterface::class),
        );
    }

    private function input(): CreateOrderInputDto
    {
        return new CreateOrderInputDto(
            str_repeat('c', 64),
            [new QuoteLineInputDto('11111111-1111-4111-8111-111111111111', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1)],
            new OrderBuyerInputDto('Анна', '+79001234567', 'buyer@example.test', '', null, true),
            [new AcceptedDocumentDto('buyer-consent', '2026-09-25'), new AcceptedDocumentDto('offer', '2026-09-25')],
        );
    }

    private function group(): GalleryGroupOutputDto
    {
        return new GalleryGroupOutputDto(1, 2, 'group-id', 'Institution', 'Shoot', 'Group', 'regular', '2026-09-21 00:00:00', '2026-09-28 00:00:00', 1);
    }

    private function validated(): ValidatedQuoteOutputDto
    {
        return new ValidatedQuoteOutputDto(new QuoteOutputDto($this->quote(25000), str_repeat('c', 64), '2026-09-22T10:15:00+00:00'), new GalleryAccessOutputDto($this->group(), 'open', '2026-09-22T10:00:00+00:00', 1, []));
    }

    /** @return array{lines: list<array<string, mixed>>, total: int, subtotal: int, discount: int, giftSaving: int, gifts: list<string>, count: int, invalid: list<string>, revision: int, conditionsRevision: int} */
    private function quote(int $total): array
    {
        return ['lines' => [], 'total' => $total, 'subtotal' => $total, 'discount' => 0, 'giftSaving' => 0, 'gifts' => [], 'count' => 1, 'invalid' => [], 'revision' => 1, 'conditionsRevision' => 1];
    }

    private function created(string $key): CreatedOrderOutputDto
    {
        $order = new OrderOutputDto(
            'order-id',
            'MF-000001',
            'institution-id',
            'Institution',
            'shoot-id',
            'Shoot',
            'group-id',
            'Group',
            'regular',
            '2026-09-22T13:00:00+03:00',
            new OrderBuyerOutputDto('Анна', '+79001234567', 'buyer@example.test', '', null),
            $this->quote(25000),
            'unpaid',
            'not-started',
            '1',
        );

        return new CreatedOrderOutputDto($order, $key, '2026-10-22T13:00:00+03:00');
    }
}
