<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class DownloadResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public ?string $expiresAt,
        public ?string $filename,
        public ?string $error,
        public ?string $contentUrl,
    ) {}
}
