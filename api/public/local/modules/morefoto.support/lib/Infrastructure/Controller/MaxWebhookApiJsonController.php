<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

/** Общая обвязка входящих событий MAX: проверка секрета подписки до action, единый error contract и no-store. */
abstract class MaxWebhookApiJsonController extends PrivateApiJsonController
{
    /** @return Base[] */
    protected function getDefaultPreFilters(): array
    {
        return [
            new MaxWebhookSecretFilter((string)(getenv('MOREFOTO_SUPPORT_MAX_WEBHOOK_SECRET') ?: '')),
            ...parent::getDefaultPreFilters(),
        ];
    }
}
