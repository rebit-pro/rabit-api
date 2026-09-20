<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Contract;

use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;

interface EmailTransportInterface
{
    public function send(DeliveryOperationDto $operation): void;
}
