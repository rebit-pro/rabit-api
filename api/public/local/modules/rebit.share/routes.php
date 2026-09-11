<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Rebit\Share\Presentation\Controller\FileController;

return static function(RoutingConfigurator $routes) {
    $routes->post('/api/v1/share/file/upload/', [FileController::class, 'uploadAction']);
};
