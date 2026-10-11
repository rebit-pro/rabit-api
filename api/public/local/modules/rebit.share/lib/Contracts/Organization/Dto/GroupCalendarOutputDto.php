<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupCalendarOutputDto
{
    /**
     * @param null|string $closesAt          окончание приёма печати (T0+7), ATOM в зоне группы
     * @param null|string $digitalSalesUntil окончание продажи электронных фото (T0+14), ATOM; null — срок не назначен
     * @param string      $status            preparing | open | digital | closed
     */
    public function __construct(
        public string $timezone,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $digitalSalesUntil,
        public ?string $deliveryDueAt,
        public string $status,
    ) {}
}
