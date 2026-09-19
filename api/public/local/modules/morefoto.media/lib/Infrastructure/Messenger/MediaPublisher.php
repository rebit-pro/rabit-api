<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Messenger;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

final readonly class MediaPublisher implements MediaPublisherInterface
{
    public function __construct(private MessagePublisherInterface $publisher) {}

    public function process(string $photoId): void
    {
        $this->publisher->dispatch(new ProcessPhotoMessage($photoId), 30);
    }
}
