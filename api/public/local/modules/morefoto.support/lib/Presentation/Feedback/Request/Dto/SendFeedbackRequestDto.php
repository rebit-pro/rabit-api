<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Feedback\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\ClientAddress;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class SendFeedbackRequestDto implements RequestDtoInterface
{
    public function __construct(
        public string $name,
        public string $contact,
        public string $message,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
        #[ClientAddress]
        public string $clientAddress,
    ) {}
}
