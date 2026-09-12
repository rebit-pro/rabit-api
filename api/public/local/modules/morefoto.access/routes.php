<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Access\Presentation\Controller\ProfileController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/me', [ProfileController::class, 'meAction']);
};
