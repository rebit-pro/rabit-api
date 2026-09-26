<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Structure\Dto;

use Morefoto\Organization\Presentation\Institution\InstitutionDetailInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class DeleteGroupRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'group_id', pattern: InstitutionDetailInputMapper::UUID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $id,
        #[RequestHeader(name: 'Authorization')]
        public string $authorization,
    ) {}
}
