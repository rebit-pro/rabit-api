<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Media\Presentation\Controller\MediaController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/shoots/{shoot_id}/photos', [MediaController::class, 'listAction']);
    $routes->post('/api/v1/shoots/{shoot_id}/photos', [MediaController::class, 'uploadAction']);
    $routes->get('/api/v1/photos/{photo_id}', [MediaController::class, 'detailAction']);
    $routes->post('/api/v1/groups/{group_id}/photo-assignments', [MediaController::class, 'assignmentAction']);
    $routes->put('/api/v1/groups/{group_id}/cover', [MediaController::class, 'coverAction']);
};
