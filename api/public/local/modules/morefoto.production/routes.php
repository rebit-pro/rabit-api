<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Production\Presentation\Controller\ProductionController;
use Morefoto\Production\Presentation\Controller\PublicArchiveController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/production/groups', [ProductionController::class, 'groupsAction']);
    $routes->get('/api/v1/groups/{group_id}/production', [ProductionController::class, 'groupAction']);
    $routes->post('/api/v1/groups/{group_id}/production/versions', [ProductionController::class, 'createAction']);
    $routes->get('/api/v1/production/versions/{version_id}', [ProductionController::class, 'versionAction']);
    $routes->get('/api/v1/production/versions/{version_id}/export', [ProductionController::class, 'exportAction']);
    $routes->post('/api/v1/production/versions/{version_id}/renewal', [ProductionController::class, 'renewalAction']);
    $routes->get('/api/v1/public/production/archives/{archive_token}', [PublicArchiveController::class, 'archiveAction']);
};
