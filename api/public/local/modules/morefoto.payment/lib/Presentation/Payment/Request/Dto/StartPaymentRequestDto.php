<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 4096)]
#[StrictRequest]
final readonly class StartPaymentRequestDto implements RequestDtoInterface
{
    public function __construct(
        public string $orderVersion,
        public string $quoteToken,
        public string $paymentMethod,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
        public ?string $precedingAttemptId = null,
        #[RequestHeader('X-Order-Key', required: false)]
        public ?string $orderKey = null,
    ) {}
}
