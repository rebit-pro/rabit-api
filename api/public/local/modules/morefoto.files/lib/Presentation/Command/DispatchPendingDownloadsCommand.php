<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Command;

use Morefoto\Files\Application\Files\UseCase\DispatchPendingDownloadsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:files:dispatch-pending', description: 'Повторная публикация незавершённых сборок ZIP MoreFoto')]
#[WithLock]
final class DispatchPendingDownloadsCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 100;

    public function __construct(private readonly DispatchPendingDownloadsUseCase $dispatch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит сборок', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $result = $this->dispatch->execute($limit);
        $io->success(sprintf('Опубликовано сборок: %d, завершено ошибкой или не опубликовано: %d.', $result->processed, $result->failed));

        return Command::SUCCESS;
    }
}
