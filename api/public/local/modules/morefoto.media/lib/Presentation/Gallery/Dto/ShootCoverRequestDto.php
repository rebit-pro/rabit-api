<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ShootCoverRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'shoot_key', pattern: '/^[a-f0-9]{32}$/D', errorCode: 'GALLERY_NOT_FOUND', errorStatus: 404)]
        public string $key,
        #[RouteParameter(name: 'group_id', pattern: '/^[a-f0-9-]{36}$/D', errorCode: 'PHOTO_NOT_FOUND', errorStatus: 404)]
        public string $groupId,
    ) {}
}
