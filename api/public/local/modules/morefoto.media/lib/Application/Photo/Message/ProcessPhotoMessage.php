<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Message;

use Rebit\Share\Application\Contract\Messenger\AbstractMessage;

final readonly class ProcessPhotoMessage extends AbstractMessage
{
    public function __construct(public string $photoId)
    {
        parent::__construct();
    }

    public function getDeduplicationKey(): string
    {
        return 'media:' . $this->photoId;
    }
}
