<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class SendGuestFeedbackInputDto
{
    public function __construct(
        public string $name,
        public string $contact,
        public string $message,
        /** IP клиента в открытом виде: только для хеша лимита, не сохраняется. */
        public string $clientAddress,
    ) {}
}
