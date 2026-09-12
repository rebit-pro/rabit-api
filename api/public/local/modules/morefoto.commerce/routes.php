<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Commerce\Presentation\Controller\CatalogController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/catalog/products', [CatalogController::class, 'listAction']);
    $routes->post('/api/v1/catalog/products', [CatalogController::class, 'createAction']);
    $routes->patch('/api/v1/catalog/products/{product_id}', [CatalogController::class, 'updateAction']);
};
