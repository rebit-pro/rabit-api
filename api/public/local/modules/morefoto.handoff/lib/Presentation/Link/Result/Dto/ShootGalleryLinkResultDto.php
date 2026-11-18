<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class ShootGalleryLinkResultDto implements ResponseDtoInterface
{
    public function __construct(public string $galleryPath) {}
}
