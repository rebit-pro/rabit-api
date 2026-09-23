<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Avatar\Dto;

use Morefoto\Access\Presentation\Avatar\AvatarInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffAvatarRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'user_id', pattern: AvatarInputMapper::USER_ID_PATTERN, errorCode: 'STAFF_NOT_FOUND', errorStatus: 404)]
        public string $userId,
    ) {}
}
