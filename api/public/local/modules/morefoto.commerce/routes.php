<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Commerce\Presentation\Controller\CatalogController;
use Morefoto\Commerce\Presentation\Controller\CatalogRemovalController;
use Morefoto\Commerce\Presentation\Controller\ConditionsController;
use Morefoto\Commerce\Presentation\Controller\GroupSalesController;
use Morefoto\Commerce\Presentation\Controller\OrderController;
use Morefoto\Commerce\Presentation\Controller\SalesReportController;
use Morefoto\Commerce\Presentation\Controller\StaffOrderController;
use Morefoto\Commerce\Presentation\Controller\StaffOrderRemovalController;
use Morefoto\Commerce\Presentation\Controller\StorefrontController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/public/galleries/{gallery_token}/catalog', [StorefrontController::class, 'catalogAction']);
    $routes->post('/api/v1/public/galleries/{gallery_token}/quotes', [StorefrontController::class, 'quoteAction']);
    $routes->post('/api/v1/public/galleries/{gallery_token}/orders', [OrderController::class, 'createAction']);
    $routes->get('/api/v1/public/orders/current', [OrderController::class, 'currentAction']);
    $routes->get('/api/v1/orders', [StaffOrderController::class, 'listAction']);
    $routes->get('/api/v1/orders/{order_id}', [StaffOrderController::class, 'detailAction']);
    $routes->delete('/api/v1/orders/{order_id}', [StaffOrderRemovalController::class, 'deleteAction']);
    $routes->get('/api/v1/catalog/products', [CatalogController::class, 'listAction']);
    $routes->post('/api/v1/catalog/products', [CatalogController::class, 'createAction']);
    $routes->patch('/api/v1/catalog/products/{product_id}', [CatalogController::class, 'updateAction']);
    $routes->delete('/api/v1/catalog/products/{product_id}', [CatalogRemovalController::class, 'deleteAction']);
    $routes->get('/api/v1/catalog/conditions', [ConditionsController::class, 'globalAction']);
    $routes->put('/api/v1/catalog/conditions', [ConditionsController::class, 'saveGlobalAction']);
    $routes->get('/api/v1/groups/{group_id}/conditions', [ConditionsController::class, 'groupAction']);
    $routes->put('/api/v1/groups/{group_id}/conditions', [ConditionsController::class, 'saveGroupAction']);
    $routes->get('/api/v1/groups/{group_id}/sales-summary', [GroupSalesController::class, 'groupAction']);
    $routes->get('/api/v1/sales-report', [SalesReportController::class, 'reportAction']);
    $routes->get('/api/v1/sales-report/pdf', [SalesReportController::class, 'pdfAction']);
    $routes->get('/api/v1/sales-report/children/{child_id}/preview', [SalesReportController::class, 'childPreviewAction']);
};
