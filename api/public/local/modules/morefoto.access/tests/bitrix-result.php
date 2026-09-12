<?php

declare(strict_types=1);

namespace Bitrix\Main\DB;

// PHPUnit-only declaration; native integration loads the real Bitrix DB Result.
if (!class_exists(Result::class)) {
    class Result
    {
        /** @return array<string, mixed>|false */
        public function fetch(): array|false
        {
            return false;
        }
    }
}
