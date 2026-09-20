<?php

declare(strict_types=1);

namespace Rebit\Notification\Presentation\Command;

use Rebit\Notification\Application\Delivery\UseCase\DispatchPendingEmailUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notification:dispatch-pending', description: 'Публикация ожидающих email-операций')]
#[WithLock]
final class DispatchPendingNotificationsCommand extends RebitCommand
{
    public function __construct(private readonly DispatchPendingEmailUseCase $dispatch)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит операций', '100')
            ->addOption(
                'include-unknown',
                null,
                InputOption::VALUE_NONE,
                'Явно повторить операции с неизвестным исходом; возможен дубль письма',
            )
        ;
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 500 < $limit) {
            $io->error('Лимит должен быть от 1 до 500.');

            return Command::INVALID;
        }
        $includeUnknown = true === $input->getOption('include-unknown');
        if ($includeUnknown) {
            $io->warning('Unknown означает, что транспорт мог принять письмо. Повтор может создать дубль.');
        }
        $io->success('Опубликовано операций: ' . $this->dispatch->execute($limit, $includeUnknown));

        return Command::SUCCESS;
    }
}
