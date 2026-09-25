<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;

/** Без StrictRequest: MED-04 исторически игнорирует query-параметры. */
final readonly class PhotoRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'photo_id', pattern: PhotoListInputMapper::ID_PATTERN)]
        public string $photoId,
    ) {}
}
