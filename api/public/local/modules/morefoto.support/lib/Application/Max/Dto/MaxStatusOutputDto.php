<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Max\Dto;

use Rebit\Share\Application\Contract\Notification\Dto\MaxBotOutputDto;

final readonly class MaxStatusOutputDto
{
    /**
     * @param list<string>                                                                  $subscriptions
     * @param list<array{chatId: int, lastEvent: string, botPresent: bool, seenAt: string}> $chats
     */
    public function __construct(
        public MaxBotOutputDto $bot,
        public array $subscriptions,
        public int $configuredChatId,
        public array $chats,
    ) {}
}
