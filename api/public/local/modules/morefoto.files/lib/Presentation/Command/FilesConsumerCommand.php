<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Command;

use Morefoto\Files\Application\Files\UseCase\ConsumeFilesUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:files:consume', description: 'Сборка ZIP купленных оригиналов MoreFoto')]
#[WithLock]
final class FilesConsumerCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 20;
    private const int DEFAULT_TIME_LIMIT = 900;

    public function __construct(private readonly ConsumeFilesUseCase $consume)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит сообщений', (string)self::DEFAULT_LIMIT)
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Лимит времени (сек)', (string)self::DEFAULT_TIME_LIMIT)
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
