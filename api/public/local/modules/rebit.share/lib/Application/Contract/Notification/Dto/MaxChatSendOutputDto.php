<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;

final readonly class MaxChatSendOutputDto
{
    public function __construct(
        public MaxSendStatusEnum $status,
        public ?string $mid = null,
        public ?string $errorCode = null,
    ) {}
}
