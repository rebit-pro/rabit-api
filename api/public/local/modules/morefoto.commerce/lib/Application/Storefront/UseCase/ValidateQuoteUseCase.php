<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\UseCase;

use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;

/** Повторно проверяет срок, ключ галереи, состав и актуальные версии сохранённого расчёта.
 * Будущий сценарий заказа должен вызывать эту проверку внутри своей атомарной операции.
 */
final readonly class ValidateQuoteUseCase
{
    public function __construct(private StorefrontQuote $calculator, private QuoteRepository $quotes, private ClockInterface $clock, private QuoteTransactionInterface $transaction) {}

    /** @param list<QuoteLineInputDto> $lines */
    public function execute(string $galleryToken, string $quoteToken, array $lines): QuoteOutputDto
    {
        return $this->transaction->execute(fn(): QuoteOutputDto => $this->executeWithinTransaction($galleryToken, $quoteToken, $lines));
    }

    /** @param list<QuoteLineInputDto> $lines */
    public function executeWithinTransaction(string $galleryToken, string $quoteToken, array $lines): QuoteOutputDto
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
            throw new HttpException('QUOTE_STALE', 409);
        }

        return new QuoteOutputDto($result['quote'], $quoteToken, $expires->format(DATE_ATOM));
    }
}
