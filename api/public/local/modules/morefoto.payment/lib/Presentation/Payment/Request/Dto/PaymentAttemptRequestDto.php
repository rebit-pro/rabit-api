<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class PaymentAttemptRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'payment_attempt_id', pattern: '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', errorCode: 'PAYMENT_ATTEMPT_NOT_FOUND', errorStatus: 404)]
        public string $attemptId,
        #[RequestHeader('X-Order-Key', required: false)]
        public ?string $orderKey = null,
    ) {}
}
