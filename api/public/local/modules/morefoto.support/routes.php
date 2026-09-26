<?php

declare(strict_types=1);
use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Support\Presentation\Controller\GalleryQuestionController;
use Morefoto\Support\Presentation\Controller\GuestFeedbackController;
use Morefoto\Support\Presentation\Controller\MaxWebhookController;
use Morefoto\Support\Presentation\Controller\StaffQuestionController;

return static function(RoutingConfigurator $routes): void {
    $routes->post('/api/v1/public/galleries/{gallery_token}/questions', [GalleryQuestionController::class, 'askAction']);
    $routes->get('/api/v1/public/questions/current', [GalleryQuestionController::class, 'currentAction']);
    $routes->post('/api/v1/public/questions/current/messages', [GalleryQuestionController::class, 'messageAction']);
    $routes->post('/api/v1/public/feedback', [GuestFeedbackController::class, 'sendAction']);
    $routes->get('/api/v1/questions/mine', [StaffQuestionController::class, 'mineAction']);
    $routes->post('/api/v1/questions/mine/messages', [StaffQuestionController::class, 'messageAction']);
    $routes->post('/api/v1/webhooks/max/updates', [MaxWebhookController::class, 'updateAction']);
};
