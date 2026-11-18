<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class ShootGalleryResultDto implements ResponseDtoInterface
{
    /** @param list<array{name: string, groupPath: string, coverUrl: ?string}> $groups */
    public function __construct(public string $shootName, public string $institutionName, public string $state, public array $groups) {}
}
