<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

/**
 * Файл применяется только в локальном окружении
 */
if ('local' !== getenv('APP_ENV')) {
    return;
}

/**
 * Автоматическая авторизация пользователя $developerId
 */
EventManager::getInstance()->addEventHandler('main', 'OnBeforeProlog', function() {
    global $USER;

    if (!$USER->IsAuthorized() && 'local' === getenv('APP_ENV')) {
        $developerId = 1;
        $USER->Authorize($developerId);
    }
});

/**
 * Отключаем модуль проактивной защиты
 */
if (
    Loader::includeModule('security')
) {
    CSecurityFilter::SetActive(false);
    CSecurityIPRule::SetActive(false);
}
