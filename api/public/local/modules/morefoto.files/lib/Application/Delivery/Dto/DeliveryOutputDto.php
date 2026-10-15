<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class DeliveryOutputDto
{
    /**
     * @param string $kind   auto | manual
     * @param string $status pending | accepted | failed | cancelled; accepted — письмо передано почтовому серверу, не прочитано
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public string $recipient,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $acceptedAt,
    ) {}
}
