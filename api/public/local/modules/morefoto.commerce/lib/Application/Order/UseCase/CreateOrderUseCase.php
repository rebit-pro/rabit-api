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
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Оформляет заказ покупателя из проверенного серверного расчёта одной атомарной операцией.
 * Повтор с тем же Idempotency-Key возвращает исходный заказ даже после закрытия приёма; новый заказ создаётся
 * только в открытой группе по актуальному расчёту и с принятыми действующими редакциями согласия и оферты,
 * а изменение цены возвращает новый расчёт для подтверждения.
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
        private ConsentRecorderInterface $consents,
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
        $state = $this->gallery->context($galleryToken)->state;
        if ('preparing' === $state) {
            throw new HttpException('GALLERY_NOT_READY', 409);
        }
        $replay = $this->receipts->reserve($galleryToken, $key, $input);
        if (null !== $replay) {
            return $replay;
        }
        if ('open' !== $state) {
            throw new HttpException('GALLERY_CLOSED', 409);
        }
        $buyer = $input->buyer;
        $accepted = $this->buyers->accept($buyer->name, $buyer->phone, $buyer->email, $buyer->comment, $buyer->receiptChannel, $buyer->reviewed, $this->availability->receiptChannels());
        $placed = $this->placement->place($this->quotes->executeWithinTransaction($galleryToken, $input->quoteToken, $input->lines), $accepted, $galleryToken, $input->quoteToken);
        // A missing or outdated document rolls the whole checkout back together with the order.
        $this->consents->record(ConsentContextEnum::ORDER, $placed->orderId, $input->consents);
        $this->receipts->complete($galleryToken, $key, $placed);

        return $placed->created;
    }
}
