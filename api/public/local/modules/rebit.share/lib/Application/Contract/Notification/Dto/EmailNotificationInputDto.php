<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

final readonly class EmailNotificationInputDto
{
    public function __construct(
        public string $consumer,
        public string $deduplicationKey,
        public string $recipient,
        public string $subject,
        public string $body,
        public int $maxAttempts = 3,
        /** Optional HTML variant built by the sender; the plain-text body stays the fallback. */
        public ?string $bodyHtml = null,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/D', $this->consumer)) {
            throw new \InvalidArgumentException('Invalid notification consumer.');
        }
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $this->deduplicationKey)) {
            throw new \InvalidArgumentException('Invalid notification deduplication key.');
        }
        if (false === filter_var($this->recipient, FILTER_VALIDATE_EMAIL) || 254 < strlen($this->recipient)) {
            throw new \InvalidArgumentException('Invalid notification recipient.');
        }
        if ('' === trim($this->subject) || 200 < mb_strlen($this->subject) || str_contains($this->subject, "\r") || str_contains($this->subject, "\n")) {
            throw new \InvalidArgumentException('Invalid notification subject.');
        }
        if ('' === trim($this->body) || 65535 < strlen($this->body)) {
            throw new \InvalidArgumentException('Invalid notification body.');
        }
        if (1 > $this->maxAttempts || 10 < $this->maxAttempts) {
            throw new \InvalidArgumentException('Invalid notification attempt limit.');
        }
    }
}
