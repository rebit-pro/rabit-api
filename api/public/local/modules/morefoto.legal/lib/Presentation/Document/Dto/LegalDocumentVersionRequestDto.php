<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class LegalDocumentVersionRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'code', pattern: '/^[a-z-]{1,32}$/D', errorCode: 'DOCUMENT_NOT_FOUND', errorStatus: 404)]
        public string $code,
        #[RouteParameter(name: 'version', pattern: '/^[0-9-]{10,13}$/D', errorCode: 'DOCUMENT_NOT_FOUND', errorStatus: 404)]
        public string $version,
    ) {}
}
