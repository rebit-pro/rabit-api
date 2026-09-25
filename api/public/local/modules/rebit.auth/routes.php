<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Rebit\Auth\Presentation\Controller\AccessLinkController;
use Rebit\Auth\Presentation\Controller\AuthController;
use Rebit\Auth\Presentation\Controller\PasswordController;

return static function(RoutingConfigurator $routes) {
    $routes->post('/api/v1/auth/login', [AuthController::class, 'loginAction']);
    $routes->post('/api/v1/auth/register/request-code', [AuthController::class, 'requestRegistrationCodeAction']);
    $routes->post('/api/v1/auth/register/confirm', [AuthController::class, 'confirmRegistrationAction']);
    $routes->post('/api/v1/auth/logout', [AuthController::class, 'logoutAction']);
    $routes->get('/api/v1/auth/invitations/{token}', [AccessLinkController::class, 'invitationAction']);
    $routes->post('/api/v1/auth/invitations/{token}/accept', [AccessLinkController::class, 'acceptInvitationAction']);
    $routes->post('/api/v1/auth/password-resets', [AccessLinkController::class, 'requestPasswordResetAction']);
    $routes->post('/api/v1/auth/password-resets/{token}/confirm', [AccessLinkController::class, 'confirmPasswordResetAction']);
    $routes->patch('/api/v1/me/password', [PasswordController::class, 'changeAction']);
};
