<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\GroupHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

/**
 * Не закрывает общие handlers канала при уничтожении временного HTTP-логгера.
 */
final class NonClosingGroupHandler extends GroupHandler
{
    public function handle(array $record): bool
    {
        foreach ($this->handlers as $handler) {
            $this->handleWithDebugLevel($handler, $record);
        }

        return false === $this->bubble;
    }

    public function handleBatch(array $records): void
    {
        foreach ($this->handlers as $handler) {
            $this->handleBatchWithDebugLevel($handler, $records);
        }
    }

    public function close(): void {}

    private function handleWithDebugLevel(HandlerInterface $handler, array $record): void
    {
        if (!$handler instanceof AbstractHandler) {
            $handler->handle($record);

            return;
        }

        $originalLevel = $handler->getLevel();
        $handler->setLevel(Logger::DEBUG);

        try {
            $handler->handle($record);
        } finally {
            $handler->setLevel($originalLevel);
        }
    }

    private function handleBatchWithDebugLevel(HandlerInterface $handler, array $records): void
    {
        if (!$handler instanceof AbstractHandler) {
            $handler->handleBatch($records);

            return;
        }

        $originalLevel = $handler->getLevel();
        $handler->setLevel(Logger::DEBUG);

        try {
            $handler->handleBatch($records);
        } finally {
            $handler->setLevel($originalLevel);
        }
    }
}
