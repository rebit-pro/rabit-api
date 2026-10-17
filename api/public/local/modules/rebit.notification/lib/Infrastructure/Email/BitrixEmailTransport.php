<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Email;

use Bitrix\Main\Config\Option;
use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;

final readonly class BitrixEmailTransport implements EmailTransportInterface
{
    public const string EVENT_NAME = 'REBIT_NOTIFICATION_OUTGOING_EMAIL';

    public function __construct(private string $siteId) {}

    public function send(DeliveryOperationDto $operation): void
    {
        $files = $this->files($operation);
        try {
            $result = \CEvent::SendImmediate(
                self::EVENT_NAME,
                $this->siteId,
                [
                    'EMAIL_TO' => $operation->recipient,
                    'SUBJECT' => htmlspecialchars($operation->subject, ENT_QUOTES, 'UTF-8'),
                    // HTML is built by the sender from escaped data; plain text is escaped here.
                    'BODY' => $operation->bodyHtml ?? nl2br(htmlspecialchars($operation->body, ENT_QUOTES, 'UTF-8')),
                ],
                filesContent: $files,
            );
        } catch (\Throwable $error) {
            throw new \RuntimeException('Email transport outcome is unknown.', 0, $error);
        }
        if ('Y' !== $result) {
            throw new DefiniteDeliveryException('email_transport_rejected');
        }
    }

    /**
     * J5: the bytes are read only now and only from the referenced file; a missing or changed file fails the attempt instead
     * of sending a letter without it. Bitrix silently swaps attachments above `main.max_file_size` for a text stub, so such
     * a letter is refused here.
     *
     * @return list<array{CONTENT_TYPE: string, NAME: string, CONTENT: string, ID: string, CHARSET: null, METHOD: null}>
     */
    private function files(DeliveryOperationDto $operation): array
    {
        if ([] === $operation->attachments) {
            return [];
        }
        $files = [];
        $encoded = 0;
        foreach ($operation->attachments as $index => $attachment) {
            $content = is_file($attachment->path) ? file_get_contents($attachment->path) : false;
            if (false === $content || strlen($content) !== $attachment->bytes) {
                throw new DefiniteDeliveryException('attachment_unavailable');
            }
            $encoded += 4 * (int)ceil($attachment->bytes / 3);
            // METHOD stays null: Bitrix treats a set METHOD as a calendar part and writes it into the Content-Type.
            $files[] = [
                'CONTENT_TYPE' => $attachment->contentType,
                'NAME' => $attachment->name,
                'CONTENT' => $content,
                'ID' => 'attachment-' . ($index + 1),
                'CHARSET' => null,
                'METHOD' => null,
            ];
        }
        $limit = (int)Option::get('main', 'max_file_size');
        if (0 < $limit && $limit < $encoded) {
            throw new DefiniteDeliveryException('attachment_limit');
        }

        return $files;
    }
}
