<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;

return static function(RoutingConfigurator $routes): void {
    foreach (['rebit.auth', 'rebit.share', 'rebit.notification'] as $moduleId) {
        $configure = require __DIR__ . '/../modules/' . $moduleId . '/routes.php';
        $configure($routes);
    }
};
