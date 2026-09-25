<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

interface QuestionKeySealInterface
{
    /** Шифрует ключ беседы Idempotency-Key клиента: повтор получает тот же ключ, а БД хранит только шифр. */
    public function seal(string $questionKey, string $idempotencyKey, string $context): string;

    public function open(string $sealed, string $idempotencyKey, string $context): string;
}
