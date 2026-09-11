<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Command;

use Rebit\Share\Presentation\Command\Attribute\WithLock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * Базовый класс для консольных команд Rebit.
 *
 * Инкапсулирует инфраструктурный бойлерплейт:
 * - создание SymfonyStyle
 * - try/catch с выводом ошибки
 * - опциональный flock через атрибут #[WithLock]
 *
 * Наследники реализуют handle() вместо execute().
 */
abstract class RebitCommand extends Command
{
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lock = $this->acquireLock();

        if (false === $lock) {
            $io->warning(sprintf('Команда "%s" уже запущена', $this->getName()));

            return Command::SUCCESS;
        }

        try {
            return $this->handle($io, $input);
        } catch (\Throwable $e) {
            $io->error('Ошибка: ' . $e->getMessage());

            return Command::FAILURE;
        } finally {
            $lock?->release();
        }
    }

    abstract protected function handle(SymfonyStyle $io, InputInterface $input): int;

    /**
     * @return null|false|LockInterface lock-объект, false если занят, null если лок не нужен
     */
    private function acquireLock(): false|LockInterface|null
    {
        $attributes = (new \ReflectionClass($this))->getAttributes(WithLock::class);

        if ([] === $attributes) {
            return null;
        }

        $lockName = $attributes[0]->newInstance()->lockName;

        if (null === $lockName) {
            $lockName = (string)$this->getName();
        }

        $lock = (new LockFactory(new FlockStore(sys_get_temp_dir())))
            ->createLock($lockName)
        ;

        return $lock->acquire() ? $lock : false;
    }
}
