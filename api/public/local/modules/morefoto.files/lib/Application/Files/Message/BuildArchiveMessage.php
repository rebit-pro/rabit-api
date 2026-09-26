<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class BuildArchiveMessage extends AbstractMessage
{
    public function __construct(public string $downloadId, public int $attempt)
    {
        parent::__construct();
    }

    /** Повторная публикация той же попытки из dispatch-pending не должна гаснуть в дедупликации, поэтому ключ содержит минуту публикации. */
    public function getDeduplicationKey(): string
    {
        return 'files:' . $this->downloadId . ':' . $this->attempt . ':' . intdiv((int)$this->createdAt, 60);
    }
}
