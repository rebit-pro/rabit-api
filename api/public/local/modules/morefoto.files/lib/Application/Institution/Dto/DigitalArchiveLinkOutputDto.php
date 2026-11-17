<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Dto;

final readonly class DigitalArchiveLinkOutputDto
{
    public function __construct(public string $ticket, public \DateTimeImmutable $expiresAt) {}
}
