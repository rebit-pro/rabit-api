<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Email;

use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;

final readonly class BitrixEmailTransport implements EmailTransportInterface
{
    public const string EVENT_NAME = 'REBIT_NOTIFICATION_OUTGOING_EMAIL';

    public function __construct(private string $siteId) {}

    public function send(DeliveryOperationDto $operation): void
    {
        try {
            $result = \CEvent::SendImmediate(
                self::EVENT_NAME,
                $this->siteId,
                [
                    'EMAIL_TO' => $operation->recipient,
                    'SUBJECT' => htmlspecialchars($operation->subject, ENT_QUOTES, 'UTF-8'),
                    'BODY' => nl2br(htmlspecialchars($operation->body, ENT_QUOTES, 'UTF-8')),
                ],
            );
        } catch (\Throwable $error) {
            throw new \RuntimeException('Email transport outcome is unknown.', 0, $error);
        }
        if ('Y' !== $result) {
            throw new DefiniteDeliveryException('email_transport_rejected');
        }
    }
}
