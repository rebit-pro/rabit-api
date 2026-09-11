<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Audit\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class AuditMessage extends AbstractMessage
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public int $userId,
        public string $action,
        public array $context = [],
    ) {
        parent::__construct();
    }
}
