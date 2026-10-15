<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Files\Presentation\Controller\PublicFileController;
use Morefoto\Files\Presentation\Controller\PublicFileDeliveryController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/public/orders/current/files', [PublicFileController::class, 'filesAction']);
    $routes->post('/api/v1/public/orders/current/downloads', [PublicFileController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}', [PublicFileController::class, 'downloadAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}/content', [PublicFileController::class, 'contentAction']);
    // J2: the letter with the link to the paid order — summary for the order page, FIL-05 repeat, FIL-06 status.
    $routes->get('/api/v1/public/orders/current/file-deliveries', [PublicFileDeliveryController::class, 'summaryAction']);
    $routes->post('/api/v1/public/orders/current/file-deliveries', [PublicFileDeliveryController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current/file-deliveries/{file_delivery_id}', [PublicFileDeliveryController::class, 'deliveryAction']);
};
