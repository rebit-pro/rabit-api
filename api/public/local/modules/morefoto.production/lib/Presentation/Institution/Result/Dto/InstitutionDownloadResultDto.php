<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class InstitutionDownloadResultDto implements ResponseDtoInterface
{
    public function __construct(
        public string $url,
        public string $expiresAt,
    ) {}
}
