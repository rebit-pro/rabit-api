<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

/** Internal institution record, not the full ORG-04 HTTP projection. */
final readonly class InstitutionOutputDto
{
    public function __construct(public string $id, public string $name, public string $address, public int $revision) {}

    /** @param array{UF_PUBLIC_ID: string, UF_NAME: string, UF_ADDRESS: string, UF_REVISION: int|string} $row */
    public static function fromRow(array $row): self
    {
        return new self($row['UF_PUBLIC_ID'], $row['UF_NAME'], $row['UF_ADDRESS'], (int)$row['UF_REVISION']);
    }
}
