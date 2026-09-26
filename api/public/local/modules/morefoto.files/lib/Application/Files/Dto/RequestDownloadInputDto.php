<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Dto;

use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;

final readonly class RequestDownloadInputDto
{
    /**
     * @param null|list<string> $photoIds       для file — ровно один кадр, для zip — подмножество или null (все доступные)
     * @param string            $idempotencyKey 32 hex в нижнем регистре
     */
    public function __construct(
        public DownloadKindEnum $kind,
        public ?array $photoIds,
        public string $idempotencyKey,
    ) {}
}
