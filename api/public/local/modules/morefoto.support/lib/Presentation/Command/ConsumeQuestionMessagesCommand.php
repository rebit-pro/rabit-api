<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Command;

use Morefoto\Support\Application\Question\UseCase\ConsumeQuestionMessagesUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:support:consume', description: 'Доставка вопросов кураторам в группу MAX')]
#[WithLock]
final class ConsumeQuestionMessagesCommand extends RebitCommand
{
    public function __construct(private readonly ConsumeQuestionMessagesUseCase $consume)
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
