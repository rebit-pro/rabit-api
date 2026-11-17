<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Message\Handler;

use Morefoto\Files\Application\Institution\Message\BuildInstitutionDigitalArchiveMessage;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveBuild;

/** Направляет сообщение очереди Files в идемпотентную сборку сохранённого служебного состава. */
final readonly class BuildInstitutionDigitalArchiveMessageHandler
{
    public function __construct(private DigitalArchiveBuild $build) {}

    public function __invoke(BuildInstitutionDigitalArchiveMessage $message): void
    {
        $this->build->execute($message->archiveId);
    }
}
