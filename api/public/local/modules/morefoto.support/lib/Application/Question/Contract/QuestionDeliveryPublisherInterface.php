<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

interface QuestionDeliveryPublisherInterface
{
    /** Сигнал worker после commit; при сбое реплику подберёт dispatcher pending. */
    public function publish(int $messageId): void;
}
