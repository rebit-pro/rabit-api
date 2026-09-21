<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\UseCase;

use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;

/** Сохраняет проверенный серверный расчёт на 15 минут без создания заказа или платежа. */
final readonly class CreateQuoteUseCase
{
    public function __construct(private StorefrontQuote $calculator, private QuoteRepository $quotes, private ClockInterface $clock, private QuoteTransactionInterface $transaction) {}

    /** @param list<QuoteLineInputDto> $lines */
    public function execute(string $galleryToken, array $lines): QuoteOutputDto
    {
        return $this->transaction->execute(fn(): QuoteOutputDto => $this->executeWithinTransaction($galleryToken, $lines));
    }

    /** @param list<QuoteLineInputDto> $lines */
    public function executeWithinTransaction(string $galleryToken, array $lines): QuoteOutputDto
    {
        $result = $this->calculator->calculate($galleryToken, $lines);
        $token = bin2hex(random_bytes(32));
        $expires = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('+15 minutes');
        $this->quotes->save(hash('sha256', $token), hash('sha256', $galleryToken), $result['fingerprint'], $result['quote'], $expires->format('Y-m-d H:i:s'));

        return new QuoteOutputDto($result['quote'], $token, $expires->format(DATE_ATOM));
    }
}
