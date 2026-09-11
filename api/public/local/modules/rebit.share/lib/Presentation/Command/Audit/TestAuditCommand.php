<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Command\Audit;

use Rebit\Share\Application\Audit\Message\AuditMessage;
use Rebit\Share\Application\Audit\Port\AuditPublisherInterface;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:test',
    description: 'Опубликовать тестовое сообщение в очередь audit',
)]
final class TestAuditCommand extends RebitCommand
{
    public function __construct(
        private readonly AuditPublisherInterface $publisher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'ID пользователя', '1')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Код действия', 'debug.audit')
        ;
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $userId = (int)$input->getOption('user-id');
        $action = (string)$input->getOption('action');

        $this->publisher->dispatch(
            new AuditMessage(
                userId: $userId,
                action: $action,
                context: [
                    'source' => 'console',
                    'timestamp' => date(DATE_ATOM),
                ],
            ),
        );

        $io->success(sprintf(
            'Сообщение AuditMessage опубликовано в очередь audit (userId=%d, action=%s)',
            $userId,
            $action,
        ));

        return Command::SUCCESS;
    }
}
