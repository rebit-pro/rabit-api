<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Console;

use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BootstrapOrganizerCommand extends Command
{
    public function __construct(private readonly BootstrapOrganizerUseCase $bootstrap, private readonly string $environment)
    {
        parent::__construct('morefoto:access:bootstrap-organizer');
    }

    protected function configure(): void
    {
        $this->setDescription('Assign the first organizer to an existing Auth identity in local/test environments.')
            ->addArgument('user-id', InputArgument::REQUIRED, 'Existing active Auth user ID')
            ->addOption('confirm-user-id', null, InputOption::VALUE_REQUIRED, 'Repeat the verified user ID to confirm the grant')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('user-id');
        if (!in_array($this->environment, ['local', 'test'], true)
            || !is_string($id) || !ctype_digit($id) || 0 >= (int)$id || (string)(int)$id !== $id
            || $id !== $input->getOption('confirm-user-id')) {
            $output->writeln('<error>Requires APP_ENV=local|test and matching positive user-id / --confirm-user-id.</error>');

            return Command::INVALID;
        }
        try {
            $created = $this->bootstrap->execute((int)$id);
        } catch (\Throwable) {
            $output->writeln('<error>Bootstrap refused or rolled back. Verify the identity, empty staff directory and applied migration.</error>');

            return Command::FAILURE;
        }
        $output->writeln($created
            ? 'Organizer assigned. Previous token revoked; sign in again.'
            : 'This identity is already an active organizer; nothing changed.');

        return Command::SUCCESS;
    }
}
