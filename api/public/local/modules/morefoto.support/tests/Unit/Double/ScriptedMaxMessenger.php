<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit\Double;

use Rebit\Share\Application\Contract\Notification\Dto\MaxChatMessageInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;

final class ScriptedMaxMessenger implements MaxChatMessengerInterface
{
    /** @var list<MaxChatMessageInputDto> */
    public array $sent = [];

    /** @param list<MaxChatSendOutputDto> $outcomes */
    public function __construct(private array $outcomes) {}

    public function send(MaxChatMessageInputDto $message): MaxChatSendOutputDto
    {
        $this->sent[] = $message;

        return array_shift($this->outcomes) ?? throw new \LogicException('No scripted MAX outcome left.');
    }
}
