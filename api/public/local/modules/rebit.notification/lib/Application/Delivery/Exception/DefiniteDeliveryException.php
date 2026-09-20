<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Exception;

final class DefiniteDeliveryException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct('Notification transport rejected the message.', 0, $previous);
    }
}
