<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Access\Presentation\Controller\ProfileController;
use Morefoto\Access\Presentation\Controller\StaffController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/me', [ProfileController::class, 'meAction']);
    $routes->get('/api/v1/users/assignment-options', [StaffController::class, 'optionsAction']);
    $routes->get('/api/v1/users', [StaffController::class, 'listAction']);
    $routes->post('/api/v1/users', [StaffController::class, 'createAction']);
    $routes->get('/api/v1/users/{user_id}', [StaffController::class, 'getAction']);
    $routes->patch('/api/v1/users/{user_id}', [StaffController::class, 'updateAction']);
};
