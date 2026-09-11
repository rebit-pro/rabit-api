<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Rebit\Notification\Presentation\Controller\LeadController;

return static function(RoutingConfigurator $routes) {
    // OPTIONS/preflight и CORS обрабатывает nginx (conf.d/default.conf).
    $routes->post('/api/v1/lead', [LeadController::class, 'submitAction']);
};
