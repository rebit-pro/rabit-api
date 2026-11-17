<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Institution\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class InstitutionDigitalArchiveTicketRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'archive_ticket', pattern: '/^[a-f0-9.-]{100,140}$/D', errorCode: 'ARCHIVE_NOT_FOUND', errorStatus: 404)]
        public string $ticket,
    ) {}
}
