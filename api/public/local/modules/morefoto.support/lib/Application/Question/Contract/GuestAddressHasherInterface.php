<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

interface GuestAddressHasherInterface
{
    /**
     * Ключевой хеш IP гостя (64 hex) для лимита обращений: открытый адрес не хранится.
     *
     * @throws \RuntimeException секрет не настроен или адрес не распознан
     */
    public function hash(string $address): string;
}
