<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Messenger;

use Morefoto\Files\Application\Files\Contract\FilesPublisherInterface;
use Morefoto\Files\Application\Files\Message\BuildArchiveMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

final readonly class FilesPublisher implements FilesPublisherInterface
{
    public function __construct(private MessagePublisherInterface $publisher) {}

    public function build(string $downloadId, int $attempt): void
    {
        $this->publisher->dispatch(new BuildArchiveMessage($downloadId, $attempt), 30);
    }
}
