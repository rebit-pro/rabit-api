<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Mapper;

use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Receipt\Dto\ReceiptItemOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\ReceiptOutputDto;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptStatusEnum;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;

/**
 * Проекция кассового чека для покупателя и сотрудника: состояние, позиции и реквизиты для проверки в приложении ФНС.
 *
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final readonly class ReceiptOutputMapper
{
    public function __construct(
        private ReceiptSnapshotMapper $snapshots,
        private PaymentOutputMapper $time,
    ) {}

    /** @param ReceiptRecord $record */
    public function receipt(array $record): ReceiptOutputDto
    {
        $items = [];
        foreach ($this->snapshots->decodeItemsJson($record['ITEMS']) as $item) {
            $items[] = new ReceiptItemOutputDto($item->description, $item->quantity, $item->amount, $item->mode->value);
        }

        return new ReceiptOutputDto(
            id: $record['PUBLIC_ID'],
            kind: $record['KIND'],
            status: ReceiptStatusEnum::from($record['STATUS'])->visible(),
            amount: $record['AMOUNT'],
            items: $items,
            createdAt: $this->time->display($record['CREATED_AT']),
            registeredAt: null === $record['REGISTERED_AT'] ? null : $this->time->display($record['REGISTERED_AT']),
            fiscalDocumentNumber: $record['FISCAL_DOCUMENT_NUMBER'],
            fiscalStorageNumber: $record['FISCAL_STORAGE_NUMBER'],
            fiscalAttribute: $record['FISCAL_ATTRIBUTE'],
            handedAt: null === $record['HANDED_AT'] ? null : $this->time->display($record['HANDED_AT']),
        );
    }
}
