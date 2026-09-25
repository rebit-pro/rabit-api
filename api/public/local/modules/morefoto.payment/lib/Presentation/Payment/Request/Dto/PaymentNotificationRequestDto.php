<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;

/** Тело уведомления провайдера не строгое: из объекта платежа берётся только ID для серверной сверки. */
#[JsonBody(maxBytes: 65536)]
final readonly class PaymentNotificationRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'provider', pattern: '/^[a-z]{2,32}$/D', errorCode: 'PROVIDER_NOT_FOUND', errorStatus: 404)]
        public string $provider,
        public string $event,
        public PaymentNotificationObjectRequestDto $object,
    ) {}
}
