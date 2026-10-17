<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Service;

use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptRules;

/** Хранит серверное решение о кассовых чеках (G2-DEC-03): включены ли «Чеки от ЮKassa» и с какими налоговыми
 * параметрами позиций. Запрошенные, но неверно настроенные чеки выключают оплату: продавать без чека нельзя, а магазин
 * с включёнными чеками всё равно отклонит платёж без корректного чека.
 */
final readonly class ReceiptSettings
{
    /** @param null|ReceiptRules $rules null — чеки не запрошены или настроены неверно */
    public function __construct(
        private bool $requested,
        private ?ReceiptRules $rules,
    ) {}

    public function enabled(): bool
    {
        return $this->requested && null !== $this->rules;
    }

    public function misconfigured(): bool
    {
        return $this->requested && null === $this->rules;
    }

    public function rules(): ReceiptRules
    {
        return $this->rules ?? throw new \LogicException('Receipts are not configured.');
    }
}
