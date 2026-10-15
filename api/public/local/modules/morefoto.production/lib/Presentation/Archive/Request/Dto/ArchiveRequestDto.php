<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ArchiveRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'archive_token', pattern: '/^[A-Za-z0-9_-]{43}$/D', errorCode: 'ARCHIVE_NOT_FOUND', errorStatus: 404)]
        public string $token,
    ) {}
}
