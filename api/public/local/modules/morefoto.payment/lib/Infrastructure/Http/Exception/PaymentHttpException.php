<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Http\Exception;

use Rebit\Share\Shared\Exception\HttpException;

/** Сбой обмена со шлюзом; код — HTTP-статус ответа шлюза или 0, если ответа не было. */
final class PaymentHttpException extends HttpException {}
