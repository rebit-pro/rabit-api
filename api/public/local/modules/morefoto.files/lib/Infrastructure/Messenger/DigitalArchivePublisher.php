<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Messenger;

use Morefoto\Files\Application\Institution\Contract\DigitalArchivePublisherInterface;
use Morefoto\Files\Application\Institution\Message\BuildInstitutionDigitalArchiveMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

final readonly class DigitalArchivePublisher implements DigitalArchivePublisherInterface
{
    public function __construct(private MessagePublisherInterface $publisher) {}

    public function build(string $id, int $attempt): void
    {
        $this->publisher->dispatch(new BuildInstitutionDigitalArchiveMessage($id, $attempt), 30);
    }
}
