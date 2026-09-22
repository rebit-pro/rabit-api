<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderBuyerResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $comment,
        public ?string $receiptChannel,
    ) {}
}
