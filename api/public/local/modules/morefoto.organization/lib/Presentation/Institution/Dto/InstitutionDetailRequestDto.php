<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Institution\Dto;

use Morefoto\Organization\Presentation\Institution\InstitutionDetailInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class InstitutionDetailRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'institution_id', pattern: InstitutionDetailInputMapper::UUID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $institutionId,
        #[RequestHeader(name: 'Authorization')]
        public string $authorization,
        public ?string $shootsPage = null,
        public ?string $groupsPage = null,
        public ?string $pageSize = null,
    ) {}
}
