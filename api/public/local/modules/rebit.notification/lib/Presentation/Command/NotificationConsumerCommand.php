<?php

declare(strict_types=1);

namespace Rebit\Notification\Presentation\Command;

use Rebit\Notification\Application\Delivery\UseCase\ConsumeEmailUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notification:consume', description: 'Доставка исходящих email-уведомлений')]
#[WithLock]
final class NotificationConsumerCommand extends RebitCommand
{
    public function __construct(private readonly ConsumeEmailUseCase $consume)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит сообщений', '100')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Лимит времени (сек)', '300')
        ;
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        $timeLimit = (int)$input->getOption('time-limit');
        if (1 > $limit || 1 > $timeLimit) {
            $io->error('Лимиты должны быть положительными.');

            return Command::INVALID;
        }
        $this->consume->execute($limit, $timeLimit);

        return Command::SUCCESS;
    }
}
