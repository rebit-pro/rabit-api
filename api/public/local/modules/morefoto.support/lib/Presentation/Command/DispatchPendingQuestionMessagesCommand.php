<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Command;

use Morefoto\Support\Application\Question\UseCase\DispatchPendingQuestionMessagesUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:support:dispatch-pending', description: 'Повторная публикация ожидающих вопросов для MAX')]
#[WithLock]
final class DispatchPendingQuestionMessagesCommand extends RebitCommand
{
    public function __construct(private readonly DispatchPendingQuestionMessagesUseCase $dispatch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит реплик', '100');
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $io->success('Опубликовано реплик: ' . $this->dispatch->execute($limit));

        return Command::SUCCESS;
    }
}
