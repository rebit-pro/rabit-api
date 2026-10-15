<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Delivery\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderEmailResultDto implements ResultDtoInterface
{
    public function __construct(
        public bool $enabled,
        public ?string $recipient,
        public ?FileDeliveryResultDto $latest,
        public bool $canResend,
        public ?string $resendAvailableAt,
    ) {}
}
