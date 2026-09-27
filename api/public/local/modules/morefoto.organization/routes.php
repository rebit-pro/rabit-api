<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Controller\InstitutionDetailController;
use Morefoto\Organization\Presentation\Controller\StructureController;
use Morefoto\Organization\Presentation\Controller\StructureRemovalController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/institutions', [InstitutionController::class, 'listAction']);
    $routes->post('/api/v1/institutions', [InstitutionController::class, 'createAction']);
    $routes->get('/api/v1/institutions/{institution_id}', [InstitutionDetailController::class, 'detailAction']);
    $routes->patch('/api/v1/institutions/{institution_id}', [InstitutionController::class, 'updateAction']);
    $routes->delete('/api/v1/institutions/{institution_id}', [StructureRemovalController::class, 'deleteInstitutionAction']);
    $routes->get('/api/v1/institutions/{institution_id}/shoots', [StructureController::class, 'listShootsAction']);
    $routes->post('/api/v1/institutions/{institution_id}/shoots', [StructureController::class, 'createShootAction']);
    $routes->get('/api/v1/shoots/{shoot_id}', [StructureController::class, 'getShootAction']);
    $routes->patch('/api/v1/shoots/{shoot_id}', [StructureController::class, 'updateShootAction']);
    $routes->delete('/api/v1/shoots/{shoot_id}', [StructureRemovalController::class, 'deleteShootAction']);
    $routes->post('/api/v1/shoots/{shoot_id}/groups', [StructureController::class, 'createGroupAction']);
    $routes->patch('/api/v1/groups/{group_id}', [StructureController::class, 'updateGroupAction']);
    $routes->delete('/api/v1/groups/{group_id}', [StructureRemovalController::class, 'deleteGroupAction']);
};
