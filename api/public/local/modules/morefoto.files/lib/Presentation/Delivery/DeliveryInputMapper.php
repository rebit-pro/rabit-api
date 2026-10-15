<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Delivery;

use Morefoto\Files\Presentation\Delivery\Request\Dto\CreateFileDeliveryRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class DeliveryInputMapper
{
    /** FIL-05 знает один канал — email заказа; ключ попытки — 32 hex. */
    public function key(CreateFileDeliveryRequestDto $request): string
    {
        if ('email' !== $request->channel) {
            throw new HttpException('INVALID_DELIVERY', 422, null, ['channel' => 'Use email.']);
        }
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $request->idempotencyKey)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }

        return strtolower($request->idempotencyKey);
    }
}
