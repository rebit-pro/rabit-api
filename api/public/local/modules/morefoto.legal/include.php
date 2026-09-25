<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Legal owns the documents and the consent journal; other modules reach it only through the shared Consent contract.
if (!Loader::includeModule('rebit.share')) {
    throw new RuntimeException('Required module is unavailable: rebit.share');
}
