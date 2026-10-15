<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Command;

use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:files:order-emails', description: 'Письма родителям со ссылкой на оплаченный заказ MoreFoto (J2)')]
#[WithLock]
final class OrderEmailsCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 100;

    public function __construct(private readonly DispatchOrderEmailsUseCase $dispatch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит заказов и писем за прогон', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $result = $this->dispatch->execute($limit);
        $io->success(sprintf('Новых писем: %d, поставлено в очередь: %d, отменено: %d.', $result->created, $result->queued, $result->cancelled));

        return Command::SUCCESS;
    }
}
