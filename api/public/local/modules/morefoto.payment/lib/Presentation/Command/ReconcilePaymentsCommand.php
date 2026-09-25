<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Command;

use Morefoto\Payment\Application\Payment\UseCase\ReconcilePaymentsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:payment:reconcile', description: 'Сверка открытых попыток оплаты с провайдером')]
#[WithLock]
final class ReconcilePaymentsCommand extends RebitCommand
{
    public function __construct(private readonly ReconcilePaymentsUseCase $reconcile)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит попыток', '100');
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $report = $this->reconcile->execute($limit);
        $io->success('Сверено попыток: ' . $report->checked . ', с ошибкой: ' . $report->failed);

        return 0 === $report->failed ? Command::SUCCESS : Command::FAILURE;
    }
}
