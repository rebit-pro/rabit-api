<?php

declare(strict_types=1);

use Rebit\Share\Infrastructure\Bitrix\ControllerBuilder;

// Подменяем класс ядра битрикса для добавления к нему DI
class_alias(
    ControllerBuilder::class,
    Bitrix\Main\Engine\ControllerBuilder::class,
);
