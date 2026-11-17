<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Files\Presentation\Controller\PublicFileController;
use Morefoto\Files\Presentation\Controller\PublicFileDeliveryController;
use Morefoto\Files\Presentation\Controller\InstitutionDigitalArchiveController;
use Morefoto\Files\Presentation\Controller\InstitutionDigitalArchiveContentController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/institutions/{institution_id}/digital-archives', [InstitutionDigitalArchiveController::class, 'listAction']);
    $routes->post('/api/v1/institutions/{institution_id}/digital-archives', [InstitutionDigitalArchiveController::class, 'createAction']);
    $routes->get('/api/v1/institution-digital-archives/{archive_id}', [InstitutionDigitalArchiveController::class, 'archiveAction']);
    $routes->get('/api/v1/institution-digital-archives/{archive_id}/download', [InstitutionDigitalArchiveController::class, 'downloadAction']);
    $routes->get('/api/v1/institution-digital-archives/{archive_id}/manifest', [InstitutionDigitalArchiveController::class, 'manifestAction']);
    $routes->get('/api/v1/institution-digital-archives/content/{archive_ticket}', [InstitutionDigitalArchiveContentController::class, 'contentAction']);
    $routes->get('/api/v1/public/orders/current/files', [PublicFileController::class, 'filesAction']);
    $routes->post('/api/v1/public/orders/current/downloads', [PublicFileController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}', [PublicFileController::class, 'downloadAction']);
    $routes->get('/api/v1/public/orders/current/downloads/{download_id}/content', [PublicFileController::class, 'contentAction']);
    // J2: the letter with the link to the paid order — summary for the order page, FIL-05 repeat, FIL-06 status.
    $routes->get('/api/v1/public/orders/current/file-deliveries', [PublicFileDeliveryController::class, 'summaryAction']);
    $routes->post('/api/v1/public/orders/current/file-deliveries', [PublicFileDeliveryController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current/file-deliveries/{file_delivery_id}', [PublicFileDeliveryController::class, 'deliveryAction']);
};
