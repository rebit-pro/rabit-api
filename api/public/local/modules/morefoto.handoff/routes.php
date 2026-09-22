<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Handoff\Presentation\Controller\GroupLinkController;
use Morefoto\Handoff\Presentation\Controller\StaffRequestController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/staff-requests', [StaffRequestController::class, 'listAction']);
    $routes->post('/api/v1/staff-requests', [StaffRequestController::class, 'createAction']);
    $routes->get('/api/v1/staff-requests/{staff_request_id}', [StaffRequestController::class, 'detailAction']);
    $routes->put('/api/v1/staff-requests/{staff_request_id}', [StaffRequestController::class, 'updateAction']);
    $routes->post('/api/v1/staff-requests/{staff_request_id}/clarifications', [StaffRequestController::class, 'clarificationAction']);
    $routes->get('/api/v1/group-links', [GroupLinkController::class, 'listAction']);
    $routes->get('/api/v1/groups/{group_id}/link', [GroupLinkController::class, 'detailAction']);
    $routes->post('/api/v1/groups/{group_id}/link-preparations', [GroupLinkController::class, 'preparationAction']);
    $routes->post('/api/v1/groups/{group_id}/link-transmissions', [GroupLinkController::class, 'transmissionAction']);
    $routes->post('/api/v1/groups/{group_id}/link-date-corrections', [GroupLinkController::class, 'dateCorrectionAction']);
};
