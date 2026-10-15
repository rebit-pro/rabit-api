<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Command;

use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:production:run', description: 'Архив оплаченной печати MoreFoto: снимок закрытых групп, сборка ZIP и удаление по сроку')]
#[WithLock]
final class RunProductionCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(private readonly RunProductionUseCase $run)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит групп и сборок за прогон', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 200 < $limit) {
            $io->error('Лимит должен быть от 1 до 200.');

            return Command::INVALID;
        }
        $result = $this->run->execute($limit);
        $io->success(sprintf(
            'Новых версий: %d, собрано архивов: %d, не собрано: %d, удалено по сроку: %d.',
            $result->scheduled,
            $result->built,
            $result->failed,
            $result->expired,
        ));

        return Command::SUCCESS;
    }
}
