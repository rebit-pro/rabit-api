<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Institution\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class InstitutionDigitalArchivesRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'institution_id', pattern: '/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D')]
        public string $institutionId,
    ) {}
}
