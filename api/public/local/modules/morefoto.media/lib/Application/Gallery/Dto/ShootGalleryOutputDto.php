<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Dto;

final readonly class ShootGalleryOutputDto
{
    /** @param list<array{id: string, name: string, key: string, hasCover: bool}> $groups */
    public function __construct(public string $shootName, public string $institutionName, public array $groups) {}
}
