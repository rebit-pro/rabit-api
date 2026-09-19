<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Controller\StructureController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/institutions', [InstitutionController::class, 'listAction']);
    $routes->post('/api/v1/institutions', [InstitutionController::class, 'createAction']);
    $routes->get('/api/v1/institutions/{institution_id}', [InstitutionController::class, 'getAction']);
    $routes->patch('/api/v1/institutions/{institution_id}', [InstitutionController::class, 'updateAction']);
    $routes->get('/api/v1/institutions/{institution_id}/shoots', [StructureController::class, 'listShootsAction']);
    $routes->post('/api/v1/institutions/{institution_id}/shoots', [StructureController::class, 'createShootAction']);
    $routes->get('/api/v1/shoots/{shoot_id}', [StructureController::class, 'getShootAction']);
    $routes->patch('/api/v1/shoots/{shoot_id}', [StructureController::class, 'updateShootAction']);
    $routes->post('/api/v1/shoots/{shoot_id}/groups', [StructureController::class, 'createGroupAction']);
    $routes->patch('/api/v1/groups/{group_id}', [StructureController::class, 'updateGroupAction']);
};
