<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class BuildInstitutionDigitalArchiveMessage extends AbstractMessage
{
    public function __construct(public string $archiveId, public int $attempt)
    {
        parent::__construct();
    }

    public function getDeduplicationKey(): string
    {
        return 'institution-digital:' . $this->archiveId . ':' . $this->attempt . ':' . intdiv((int)$this->createdAt, 60);
    }
}
