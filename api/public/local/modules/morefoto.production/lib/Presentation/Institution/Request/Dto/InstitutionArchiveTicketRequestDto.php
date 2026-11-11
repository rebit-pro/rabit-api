<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class InstitutionArchiveTicketRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'archive_ticket', pattern: '/^[A-Za-z0-9_.-]{80,180}$/D', errorCode: 'ARCHIVE_NOT_FOUND', errorStatus: 404)]
        public string $ticket,
    ) {}
}
