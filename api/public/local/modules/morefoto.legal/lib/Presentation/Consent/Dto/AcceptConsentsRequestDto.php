<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Consent\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;
use Rebit\Share\Presentation\Consent\Dto\AcceptedDocumentRequestDto;

#[JsonBody]
#[StrictRequest]
final readonly class AcceptConsentsRequestDto implements RequestDtoInterface
{
    /** @param list<AcceptedDocumentRequestDto> $consents */
    public function __construct(
        /** @var AcceptedDocumentRequestDto[] */
        public array $consents,
    ) {}
}
