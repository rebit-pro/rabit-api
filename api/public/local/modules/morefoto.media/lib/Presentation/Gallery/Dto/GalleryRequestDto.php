<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class GalleryRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'gallery_token', pattern: '/^[a-f0-9]{64}$/D', errorCode: 'GALLERY_NOT_FOUND', errorStatus: 404)]
        public string $token,
    ) {}
}
