<?php

declare(strict_types=1);

namespace Rebit\Auth\Presentation\Access\Dto;

use Rebit\Auth\Presentation\Access\AccessInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class AccessInvitationRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'token', pattern: AccessInputMapper::TOKEN_PATTERN, errorCode: 'LINK_NOT_FOUND', errorStatus: 404)]
        public string $token,
    ) {}
}
