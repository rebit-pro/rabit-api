<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ChildPreviewRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'child_id', pattern: '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', errorCode: 'CHILD_NOT_FOUND', errorStatus: 404)]
        public string $childId,
    ) {}
}
