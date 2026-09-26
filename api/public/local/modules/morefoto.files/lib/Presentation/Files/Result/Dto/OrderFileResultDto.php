<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderFileResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $photoId,
        public string $code,
        public string $childCode,
        public string $filename,
        public string $mimeType,
        public int $bytes,
    ) {}
}
