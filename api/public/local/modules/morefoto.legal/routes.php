<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Legal\Presentation\Controller\LegalDocumentController;
use Morefoto\Legal\Presentation\Controller\StaffConsentController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/public/legal/documents', [LegalDocumentController::class, 'listAction']);
    $routes->get('/api/v1/public/legal/documents/{code}', [LegalDocumentController::class, 'documentAction']);
    $routes->get('/api/v1/public/legal/documents/{code}/versions/{version}', [LegalDocumentController::class, 'versionAction']);
    $routes->get('/api/v1/legal/consents/pending', [StaffConsentController::class, 'pendingAction']);
    $routes->post('/api/v1/legal/consents', [StaffConsentController::class, 'acceptAction']);
};
