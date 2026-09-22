<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\UseCase;

use Morefoto\Commerce\Application\Order\Contract\OrderTransactionInterface;
use Morefoto\Commerce\Application\Order\Dto\CreatedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Service\CheckoutAvailability;
use Morefoto\Commerce\Application\Order\Service\CheckoutReceipts;
use Morefoto\Commerce\Application\Order\Service\OrderPlacement;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Order\Service\BuyerPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Storefront\Exception\QuotePriceChangedException;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Оформляет заказ покупателя из проверенного серверного расчёта одной атомарной операцией.
 * Повтор с тем же Idempotency-Key возвращает исходный заказ даже после закрытия приёма; новый заказ создаётся
 * только в открытой группе по актуальному расчёту, а изменение цены возвращает новый расчёт для подтверждения.
 */
final readonly class CreateOrderUseCase
{
    public function __construct(
        private CheckoutAvailability $availability,
        private GalleryAccessInterface $gallery,
        private CheckoutReceipts $receipts,
        private BuyerPolicy $buyers,
        private ValidateQuoteUseCase $quotes,
        private CreateQuoteUseCase $freshQuotes,
        private OrderPlacement $placement,
        private OrderTransactionInterface $transaction,
    ) {}

    public function execute(string $galleryToken, IdempotencyKey $key, CreateOrderInputDto $input): CreatedOrderOutputDto
    {
        if (!$this->availability->enabled()) {
            throw new HttpException('PURCHASE_DISABLED', 403);
        }
        try {
            return $this->transaction->execute(fn(): CreatedOrderOutputDto => $this->place($galleryToken, $key, $input));
        } catch (QuotePriceChangedException) {
            $fresh = $this->freshQuotes->execute($galleryToken, $input->lines);

            throw new HttpException('PRICE_CHANGED', 409, null, ['quote' => $fresh->quote, 'quoteToken' => $fresh->quoteToken, 'expiresAt' => $fresh->expiresAt]);
        }
    }

    private function place(string $galleryToken, IdempotencyKey $key, CreateOrderInputDto $input): CreatedOrderOutputDto
    {
        if ('preparing' === $this->gallery->context($galleryToken)->state) {
            throw new HttpException('GALLERY_NOT_READY', 409);
        }
        $replay = $this->receipts->reserve($galleryToken, $key, $input);
        if (null !== $replay) {
            return $replay;
        }
        $buyer = $input->buyer;
        $accepted = $this->buyers->accept($buyer->name, $buyer->phone, $buyer->email, $buyer->comment, $buyer->receiptChannel, $buyer->reviewed, $this->availability->receiptChannels());
        $placed = $this->placement->place($this->quotes->executeWithinTransaction($galleryToken, $input->quoteToken, $input->lines), $accepted, $galleryToken, $input->quoteToken);
        $this->receipts->complete($galleryToken, $key, $placed);

        return $placed->created;
    }
}
