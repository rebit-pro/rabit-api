<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class SetGroupCoverRequestDto implements RequestDtoInterface
{
    public function __construct(
        public int $revision,
        public string $photoId,
        #[RouteParameter(name: 'group_id', pattern: PhotoListInputMapper::ID_PATTERN)]
        public string $groupId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
