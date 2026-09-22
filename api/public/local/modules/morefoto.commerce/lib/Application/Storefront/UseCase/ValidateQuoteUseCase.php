<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\UseCase;

use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Dto\ValidatedQuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Domain\Storefront\Exception\QuotePriceChangedException;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Повторно проверяет срок, ключ галереи, состав и актуальные версии сохранённого расчёта перед оформлением заказа.
 * Сценарий заказа вызывает проверку внутри своей атомарной операции и получает проверенный контекст галереи;
 * изменение денег при том же составе отличается от прочего устаревания расчёта.
 *
 * @phpstan-import-type CartQuote from QuoteOutputDto
 */
final readonly class ValidateQuoteUseCase
{
    public function __construct(private StorefrontQuote $calculator, private QuoteRepository $quotes, private ClockInterface $clock, private QuoteTransactionInterface $transaction) {}

    /** @param list<QuoteLineInputDto> $lines */
    public function execute(string $galleryToken, string $quoteToken, array $lines): ValidatedQuoteOutputDto
    {
        return $this->transaction->execute(fn(): ValidatedQuoteOutputDto => $this->executeWithinTransaction($galleryToken, $quoteToken, $lines));
    }

    /** @param list<QuoteLineInputDto> $lines */
    public function executeWithinTransaction(string $galleryToken, string $quoteToken, array $lines): ValidatedQuoteOutputDto
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $quoteToken)) {
            throw new HttpException('QUOTE_STALE', 409);
        }
        $row = $this->quotes->find(hash('sha256', $quoteToken));
        if (false === $row || !hash_equals($row['GALLERY_HASH'], hash('sha256', $galleryToken))) {
            throw new HttpException('QUOTE_STALE', 409);
        }
        $expires = new \DateTimeImmutable($row['EXPIRES_AT'], new \DateTimeZone('UTC'));
        if ($expires <= $this->clock->now()) {
            throw new HttpException('QUOTE_EXPIRED', 409);
        }
        $result = $this->calculator->calculate($galleryToken, $lines);
        if (!hash_equals($row['FINGERPRINT'], $result['fingerprint'])) {
            if ($this->moneyChanged($row['SNAPSHOT_JSON'], $result['quote'])) {
                throw new QuotePriceChangedException('Quote money changed after recalculation.');
            }
            throw new HttpException('QUOTE_STALE', 409);
        }

        return new ValidatedQuoteOutputDto(new QuoteOutputDto($result['quote'], $quoteToken, $expires->format(DATE_ATOM)), $result['gallery']);
    }

    /** @param CartQuote $current */
    private function moneyChanged(string $snapshot, array $current): bool
    {
        try {
            $stored = json_decode($snapshot, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return !is_array($stored) || $this->money($stored) !== $this->money($current);
    }

    /**
     * @param array<array-key, mixed> $quote
     *
     * @return array<string, mixed>
     */
    private function money(array $quote): array
    {
        $lines = [];
        foreach (is_array($quote['lines'] ?? null) ? $quote['lines'] : [] as $line) {
            $lines[] = is_array($line) ? [
                $line['id'] ?? null, $line['quantity'] ?? null, $line['unitPrice'] ?? null,
                $line['total'] ?? null, $line['discount'] ?? null, $line['coveredByGift'] ?? null,
            ] : null;
        }

        return [
            'lines' => $lines,
            'gifts' => $quote['gifts'] ?? null,
            'subtotal' => $quote['subtotal'] ?? null,
            'discount' => $quote['discount'] ?? null,
            'giftSaving' => $quote['giftSaving'] ?? null,
            'total' => $quote['total'] ?? null,
        ];
    }
}
