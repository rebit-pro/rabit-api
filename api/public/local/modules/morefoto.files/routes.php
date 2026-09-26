<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Files\Presentation\Controller\PublicFileController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/public/orders/current/files', [PublicFileController::class, 'filesAction']);
    $routes->post('/api/v1/public/orders/current/downloads', [PublicFileController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}', [PublicFileController::class, 'downloadAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}/content', [PublicFileController::class, 'contentAction']);
};
