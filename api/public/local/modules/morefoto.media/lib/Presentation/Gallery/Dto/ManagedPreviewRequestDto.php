<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ManagedPreviewRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'photo_id', pattern: '/^[a-f0-9-]{36}$/D')]
        public string $photoId,
        #[RouteParameter(name: 'variant', pattern: '/^(thumb|preview)$/D')]
        public string $variant,
    ) {}
}
