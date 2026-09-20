<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Command;

use Morefoto\Media\Application\Photo\UseCase\ConsumeMediaUseCase;
use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Rebit\Share\Presentation\Command\RebitCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:consume', description: 'Обработка приватных оригиналов MoreFoto')]
#[WithLock]
final class MediaConsumerCommand extends RebitCommand
{
    private const int DEFAULT_LIMIT = 100;
    private const int DEFAULT_TIME_LIMIT = 300;

    public function __construct(private readonly ConsumeMediaUseCase $consume)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Лимит сообщений', (string)self::DEFAULT_LIMIT)
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Лимит времени (сек)', (string)self::DEFAULT_TIME_LIMIT)
        ;
    }

    protected function handle(SymfonyStyle $io, InputInterface $input): int
    {
        $limit = (int)$input->getOption('limit');
        $timeLimit = (int)$input->getOption('time-limit');
        if (1 > $limit || 1 > $timeLimit) {
            $io->error('Лимиты должны быть положительными.');

            return Command::INVALID;
        }
        $this->consume->execute($limit, $timeLimit);

        return Command::SUCCESS;
    }
}
