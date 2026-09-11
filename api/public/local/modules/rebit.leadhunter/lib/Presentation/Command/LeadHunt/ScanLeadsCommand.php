<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Presentation\Command\LeadHunt;

use Rebit\Leadhunter\Application\LeadHunt\UseCase\ScanLeadsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI-команда одного прогона охоты за внешними заявками (запускается по cron).
 */
#[AsCommand(
    name: 'app:leadhunter:scan',
    description: 'Сканирование внешних площадок и отправка подходящих заявок в Telegram',
)]
#[WithLock]
final class ScanLeadsCommand extends RebitCommand
{
    public function __construct(
        private readonly ScanLeadsUseCase $scanUseCase,
    ) {
        parent::__construct();
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $result = $this->scanUseCase->execute();

        $io->success(sprintf(
            'Совпадений: %d, новых: %d, отправлено: %d, провалено: %d',
            $result->matched,
            $result->added,
            $result->sent,
            $result->failed,
        ));

        return Command::SUCCESS;
    }
}
