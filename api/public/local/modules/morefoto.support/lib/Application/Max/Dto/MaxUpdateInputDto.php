<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Max\Dto;

final readonly class MaxUpdateInputDto
{
    public function __construct(
        public string $updateType,
        public ?int $chatId,
        public ?string $chatType,
        public ?string $mid,
        public ?string $replyToMid,
        public ?string $text,
        public string $senderName,
        public bool $senderIsBot,
    ) {}
}
