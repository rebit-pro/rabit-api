<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

final readonly class MaxMessageRequestDto
{
    public function __construct(
        public MaxRecipientRequestDto $recipient,
        public MaxMessageBodyRequestDto $body,
        public ?MaxUserRequestDto $sender = null,
        public ?MaxLinkRequestDto $link = null,
    ) {}
}
