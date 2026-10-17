<?php

declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Morefoto\Payment\Presentation\Controller\PaymentWebhookController;
use Morefoto\Payment\Presentation\Controller\PublicPaymentController;
use Morefoto\Payment\Presentation\Controller\StaffPaymentController;
use Morefoto\Payment\Presentation\Controller\StaffPrintHandoverController;

return static function(RoutingConfigurator $routes): void {
    $routes->get('/api/v1/public/orders/current/payment-quote', [PublicPaymentController::class, 'quoteAction']);
    $routes->post('/api/v1/public/orders/current/payment-attempts', [PublicPaymentController::class, 'startAction']);
    $routes->get('/api/v1/public/orders/current/payment-attempts/{payment_attempt_id}', [PublicPaymentController::class, 'attemptAction']);
    $routes->get('/api/v1/public/orders/current/receipt', [PublicPaymentController::class, 'receiptAction']);
    $routes->post('/api/v1/webhooks/{provider}/payments', [PaymentWebhookController::class, 'paymentsAction']);
    $routes->get('/api/v1/payments', [StaffPaymentController::class, 'listAction']);
    $routes->get('/api/v1/payments/{payment_attempt_id}', [StaffPaymentController::class, 'detailAction']);
    $routes->get('/api/v1/groups/{group_id}/print-handovers', [StaffPrintHandoverController::class, 'handoversAction']);
    $routes->post('/api/v1/groups/{group_id}/print-handovers', [StaffPrintHandoverController::class, 'handOverAction']);
};
