<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Organization\Presentation\Controller\InstitutionController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/institutions', [InstitutionController::class, 'listAction']);
    $routes->post('/api/v1/institutions', [InstitutionController::class, 'createAction']);
    $routes->patch('/api/v1/institutions/{institution_id}', [InstitutionController::class, 'updateAction']);
};
