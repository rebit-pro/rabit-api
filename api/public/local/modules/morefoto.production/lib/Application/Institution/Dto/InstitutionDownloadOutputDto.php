<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Dto;

final readonly class InstitutionDownloadOutputDto
{
    public function __construct(public string $ticket, public \DateTimeImmutable $expiresAt) {}
}
