<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class OrderReceiptsOutputDto
{
    /**
     * @param bool                   $enabled  чеки магазина включены
     * @param null|string            $channel  email — чеки приходят на email заказа
     * @param list<ReceiptOutputDto> $receipts чеки заказа в порядке создания
     */
    public function __construct(
        public bool $enabled,
        public ?string $channel,
        public array $receipts,
    ) {}
}
