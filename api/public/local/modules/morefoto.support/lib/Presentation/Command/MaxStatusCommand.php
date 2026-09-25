<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Command;

use Morefoto\Support\Application\Max\UseCase\GetMaxStatusUseCase;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:support:max-status', description: 'Бот MAX: профиль по токену, подписки webhook и замеченные группы')]
final class MaxStatusCommand extends RebitCommand
{
    public function __construct(private readonly GetMaxStatusUseCase $status)
    {
        parent::__construct();
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $status = $this->status->execute();
        $io->definitionList(
            ['Бот' => '@' . $status->bot->username . ' (' . $status->bot->name . ', id ' . $status->bot->userId . ')'],
            ['Webhook' => [] === $status->subscriptions ? 'нет подписок' : implode(', ', $status->subscriptions)],
            ['Группа кураторов' => 0 === $status->configuredChatId ? 'MOREFOTO_SUPPORT_MAX_CHAT_ID не задан' : (string)$status->configuredChatId],
        );
        $io->table(
            ['chat_id', 'Событие', 'Бот в чате', 'Время (UTC)'],
            array_map(static fn(array $chat): array => [$chat['chatId'], $chat['lastEvent'], $chat['botPresent'] ? 'да' : 'нет', $chat['seenAt']], $status->chats),
        );

        return Command::SUCCESS;
    }
}
