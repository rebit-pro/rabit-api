<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Dto;

/** Продавец и оператор ПДн; published — заданы все обязательные реквизиты. */
final readonly class SellerOutputDto
{
    public function __construct(
        public bool $published,
        public ?string $name,
        public ?string $inn,
        public ?string $ogrnip,
        public ?string $address,
        public ?string $email,
        public ?string $phone,
    ) {}
}
