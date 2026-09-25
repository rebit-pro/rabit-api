<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Media\Presentation\Controller\GalleryController;
use Morefoto\Media\Presentation\Controller\ManagedPreviewController;
use Morefoto\Media\Presentation\Controller\ChildTransferController;
use Morefoto\Media\Presentation\Controller\GroupMediaController;
use Morefoto\Media\Presentation\Controller\PhotoDetailController;
use Morefoto\Media\Presentation\Controller\PhotoListController;
use Morefoto\Media\Presentation\Controller\PhotoUploadController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/photos/{photo_id}/{variant}', [ManagedPreviewController::class, 'getAction']);
    $routes->get('/api/v1/public/galleries/{gallery_token}/photos/{assignment_id}/{variant}', [GalleryController::class, 'previewAction']);
    $routes->get('/api/v1/public/galleries/{gallery_token}', [GalleryController::class, 'getAction']);
    $routes->get('/api/v1/shoots/{shoot_id}/photos', [PhotoListController::class, 'listAction']);
    $routes->post('/api/v1/shoots/{shoot_id}/photos', [PhotoUploadController::class, 'uploadAction']);
    $routes->get('/api/v1/photos/{photo_id}', [PhotoDetailController::class, 'detailAction']);
    $routes->post('/api/v1/groups/{group_id}/photo-assignments', [GroupMediaController::class, 'assignmentAction']);
    $routes->put('/api/v1/groups/{group_id}/cover', [GroupMediaController::class, 'coverAction']);
    $routes->post('/api/v1/shoots/{shoot_id}/child-transfers', [ChildTransferController::class, 'transferAction']);
};
