<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class DeliverQuestionMessage extends AbstractMessage
{
    public function __construct(public int $messageId)
    {
        parent::__construct();
    }

    public function getDeduplicationKey(): string
    {
        return hash('sha256', self::class . ':' . $this->messageId);
    }
}
