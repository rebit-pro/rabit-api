<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Command;

use Morefoto\Media\Application\Photo\UseCase\DispatchPendingPhotoJobsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:dispatch-pending', description: 'Повторная публикация незапущенной обработки MoreFoto')]
#[WithLock]
final class DispatchPendingMediaCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 100;

    public function __construct(private readonly DispatchPendingPhotoJobsUseCase $dispatch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит фотографий', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $result = $this->dispatch->execute($limit);
        $io->success(sprintf('Опубликовано задач: %d, ошибок: %d.', $result->published, $result->failed));

        return Command::SUCCESS;
    }
}
