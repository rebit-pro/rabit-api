<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Command;

use Morefoto\Files\Application\Files\UseCase\PurgeDownloadsUseCase;
use Morefoto\Files\Application\Institution\UseCase\MaintainInstitutionDigitalArchivesUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:files:purge', description: 'Удаление просроченных ZIP купленных оригиналов MoreFoto')]
#[WithLock]
final class PurgeDownloadsCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 200;

    public function __construct(private readonly PurgeDownloadsUseCase $purge, private readonly MaintainInstitutionDigitalArchivesUseCase $digital)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит загрузок', (string)self::DEFAULT_LIMIT);
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        if (1 > $limit || 1000 < $limit) {
            $io->error('Лимит должен быть от 1 до 1000.');

            return Command::INVALID;
        }
        $result = $this->purge->execute($limit);
        $digital = $this->digital->purge($limit);
        $failed = $result->failed + $digital->failed;
        $message = sprintf('Убрано загрузок: %d, ошибок: %d.', $result->processed + $digital->processed, $failed);
        if (0 !== $failed) {
            $io->warning($message);

            return Command::FAILURE;
        }
        $io->success($message);

        return Command::SUCCESS;
    }
}
