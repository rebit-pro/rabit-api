<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

final readonly class MaxChatMessageInputDto
{
    public function __construct(
        public int $chatId,
        public string $text,
    ) {}
}
