<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification;

use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;

interface EmailNotificationInterface
{
    public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto;

    public function status(string $operationId): ?NotificationOperationOutputDto;
}
