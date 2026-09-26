<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class SendGuestFeedbackInputDto
{
    public function __construct(
        public string $name,
        public string $contact,
        public string $message,
    ) {}
}
