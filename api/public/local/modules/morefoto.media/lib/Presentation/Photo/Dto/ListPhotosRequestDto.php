<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ListPhotosRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'shoot_id', pattern: PhotoListInputMapper::ID_PATTERN)]
        public string $shootId,
        public ?string $groupId = null,
        public ?string $childCode = null,
        public ?string $assigned = null,
        public ?string $status = null,
        public int $page = 1,
        public int $pageSize = 50,
    ) {}
}
