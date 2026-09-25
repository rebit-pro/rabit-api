<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Max\UseCase;

use Morefoto\Support\Application\Max\Dto\MaxStatusOutputDto;
use Morefoto\Support\Domain\Question\Repository\MaxChatRepositoryInterface;
use Rebit\Share\Application\Contract\Notification\MaxBotAdminInterface;

/** Показывает оператору, что бот MAX настроен: профиль по токену, подписки webhook, выбранную и замеченные группы. */
final readonly class GetMaxStatusUseCase
{
    public function __construct(
        private MaxBotAdminInterface $admin,
        private MaxChatRepositoryInterface $chats,
        private int $chatId,
    ) {}

    public function execute(): MaxStatusOutputDto
    {
        return new MaxStatusOutputDto($this->admin->bot(), $this->admin->subscriptions(), $this->chatId, $this->chats->recent(20));
    }
}
