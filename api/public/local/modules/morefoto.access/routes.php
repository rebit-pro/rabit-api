<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Access\Presentation\Controller\ProfileController;
use Morefoto\Access\Presentation\Controller\StaffAvatarController;
use Morefoto\Access\Presentation\Controller\StaffController;
use Morefoto\Access\Presentation\Controller\StaffInvitationController;
use Morefoto\Access\Presentation\Controller\StaffListController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/me', [ProfileController::class, 'meAction']);
    $routes->put('/api/v1/me/avatar', [StaffAvatarController::class, 'saveMineAction']);
    $routes->delete('/api/v1/me/avatar', [StaffAvatarController::class, 'deleteMineAction']);
    $routes->get('/api/v1/users/assignment-options', [StaffController::class, 'optionsAction']);
    $routes->get('/api/v1/users', [StaffListController::class, 'listAction']);
    $routes->post('/api/v1/users', [StaffController::class, 'createAction']);
    $routes->get('/api/v1/users/{user_id}', [StaffController::class, 'getAction']);
    $routes->patch('/api/v1/users/{user_id}', [StaffController::class, 'updateAction']);
    $routes->post('/api/v1/users/{user_id}/invitations', [StaffInvitationController::class, 'resendAction']);
    $routes->put('/api/v1/users/{user_id}/avatar', [StaffAvatarController::class, 'saveAction']);
    $routes->delete('/api/v1/users/{user_id}/avatar', [StaffAvatarController::class, 'deleteAction']);
    $routes->get('/api/v1/users/{user_id}/avatar/{variant}', [StaffAvatarController::class, 'imageAction']);
};
