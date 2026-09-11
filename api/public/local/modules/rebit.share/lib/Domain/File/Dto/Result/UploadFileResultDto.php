<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Dto\Result;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class UploadFileResultDto implements ResponseDtoInterface
{
    public function __construct(
        public int $id,
        public string $name,
        public int $size,
        public string $type,
        public string $src,
    ) {}
}
