<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Consent\Dto;

final readonly class AcceptedDocumentDto
{
    public function __construct(
        public string $code,
        public string $version,
    ) {}
}
