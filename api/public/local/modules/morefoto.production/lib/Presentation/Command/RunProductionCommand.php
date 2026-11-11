<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Command;

use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Morefoto\Production\Application\Institution\UseCase\RunInstitutionProductionUseCase;
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

    public function __construct(private readonly RunProductionUseCase $run, private readonly RunInstitutionProductionUseCase $institutions)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит новых версий, сборок и удалений за прогон', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 200 < $limit) {
            $io->error('Лимит должен быть от 1 до 200.');

            return Command::INVALID;
        }
        $result = $this->run->execute($limit);
        $institutionResult = $this->institutions->execute($limit);
        $io->success(sprintf(
            'Новых версий: %d, собрано архивов: %d, не собрано: %d, удалено по сроку: %d.',
            $result->scheduled,
            $result->built,
            $result->failed,
            $result->expired,
        ));
        $io->success(sprintf(
            'Учреждения — новых выпусков: %d, собрано: %d, ошибок: %d, удалено: %d.',
            $institutionResult->scheduled,
            $institutionResult->built,
            $institutionResult->failed,
            $institutionResult->expired,
        ));

        return Command::SUCCESS;
    }
}
