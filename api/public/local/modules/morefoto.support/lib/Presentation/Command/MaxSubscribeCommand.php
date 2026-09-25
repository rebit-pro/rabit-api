<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Command;

use Morefoto\Support\Application\Max\UseCase\SubscribeMaxWebhookUseCase;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:support:max-subscribe', description: 'Подписать бота MAX на webhook стенда')]
final class MaxSubscribeCommand extends RebitCommand
{
    public function __construct(private readonly SubscribeMaxWebhookUseCase $subscribe)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'https://<host>/api/v1/webhooks/max/updates');
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $this->subscribe->execute((string)$input->getOption('url'));
        $io->success('Подписка webhook MAX создана.');

        return Command::SUCCESS;
    }
}
