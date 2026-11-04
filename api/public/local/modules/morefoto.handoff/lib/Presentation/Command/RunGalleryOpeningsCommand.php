<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Command;

use Morefoto\Handoff\Application\Link\UseCase\RunGalleryOpeningsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:gallery:open-scheduled', description: 'Открыть готовые галереи по сохранённому расписанию')]
#[WithLock]
final class RunGalleryOpeningsCommand extends RebitCommand
{
    public function __construct(private readonly RunGalleryOpeningsUseCase $run)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Число галерей за прогон', '100');
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 200 < $limit) {
            $io->error('Лимит должен быть от 1 до 200.');

            return Command::INVALID;
        }
        $result = $this->run->execute($limit);
        $io->success(sprintf('Открыто: %d, остановлено из-за готовности: %d, пропущено: %d.', $result->opened, $result->blocked, $result->skipped));

        return Command::SUCCESS;
    }
}
