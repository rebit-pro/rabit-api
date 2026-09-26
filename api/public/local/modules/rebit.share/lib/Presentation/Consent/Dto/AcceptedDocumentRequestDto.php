<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Consent\Dto;

/** Принятая редакция юридического документа в теле запроса: {code, version}. */
final readonly class AcceptedDocumentRequestDto
{
    public function __construct(
        public string $code,
        public string $version,
    ) {}
}
