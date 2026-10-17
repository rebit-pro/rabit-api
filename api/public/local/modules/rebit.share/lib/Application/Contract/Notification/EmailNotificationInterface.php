<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification;

use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;

interface EmailNotificationInterface
{
    /** Исходные байты всех вложений письма: после base64 ≈27 МБ, ниже лимита письма почтовых серверов в 30 МБ. */
    public const int MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto;

    public function status(string $operationId): ?NotificationOperationOutputDto;
}
