<?php

declare(strict_types=1);

namespace Bitrix\Main\DB;

// PHPUnit-only seam, guarded for coexistence with later shared Bitrix stubs.
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
