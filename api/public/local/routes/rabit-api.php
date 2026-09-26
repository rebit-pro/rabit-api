<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;

return static function(RoutingConfigurator $routes): void {
    $organizationRoutes = require __DIR__ . '/../modules/morefoto.organization/routes.php';
    $organizationRoutes($routes);
    $mediaRoutes = require __DIR__ . '/../modules/morefoto.media/routes.php';
    $mediaRoutes($routes);
    foreach (['rebit.auth', 'rebit.share', 'rebit.notification', 'morefoto.access'] as $moduleId) {
        $configure = require __DIR__ . '/../modules/' . $moduleId . '/routes.php';
        $configure($routes);
    }
    $handoffRoutes = require __DIR__ . '/../modules/morefoto.handoff/routes.php';
    $handoffRoutes($routes);
    $configureCommerce = require __DIR__ . '/../modules/morefoto.commerce/routes.php';
    $configureCommerce($routes);
    $configurePayment = require __DIR__ . '/../modules/morefoto.payment/routes.php';
    $configurePayment($routes);
    $configureFiles = require __DIR__ . '/../modules/morefoto.files/routes.php';
    $configureFiles($routes);
    $configureSupport = require __DIR__ . '/../modules/morefoto.support/routes.php';
    $configureSupport($routes);
    $configureLegal = require __DIR__ . '/../modules/morefoto.legal/routes.php';
    $configureLegal($routes);
};
