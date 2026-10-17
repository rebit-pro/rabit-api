<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Config;

use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptRules;
use Psr\Log\LoggerInterface;

/** Читает настройки кассовых чеков из окружения (G2-DEC-03); неверное значение при запрошенных чеках пишется в лог. */
final readonly class ReceiptSettingsFactory
{
    private const string PROVIDER = 'yookassa';
    private const string OFF = 'off';

    public function __construct(private LoggerInterface $logger) {}

    /**
     * @param string $mode    off | yookassa; пусто — выключено
     * @param string $vatCode ставка НДС провайдера, по умолчанию 1 — «Без НДС»
     */
    public function create(string $mode, string $vatCode, string $printSubject, string $digitalSubject): ReceiptSettings
    {
        $mode = trim($mode);
        $vatCode = '' === trim($vatCode) ? '1' : trim($vatCode);
        $print = PaymentSubjectEnum::tryFrom('' === trim($printSubject) ? PaymentSubjectEnum::COMMODITY->value : trim($printSubject));
        $digital = PaymentSubjectEnum::tryFrom('' === trim($digitalSubject) ? PaymentSubjectEnum::COMMODITY->value : trim($digitalSubject));
        $requested = '' !== $mode && self::OFF !== $mode;
        $vatValid = 1 === preg_match('/^\d{1,2}$/D', $vatCode) && ReceiptRules::MIN_VAT_CODE <= (int)$vatCode && ReceiptRules::MAX_VAT_CODE >= (int)$vatCode;
        if (!$requested) {
            return new ReceiptSettings(false, null);
        }
        if (self::PROVIDER !== $mode || !$vatValid || null === $print || null === $digital) {
            $this->logger->error('Receipt settings are invalid: payment is disabled.', ['mode' => $mode]);

            return new ReceiptSettings(true, null);
        }

        return new ReceiptSettings(true, new ReceiptRules((int)$vatCode, $print, $digital));
    }
}
