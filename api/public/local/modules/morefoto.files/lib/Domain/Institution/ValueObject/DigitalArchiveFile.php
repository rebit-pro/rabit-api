<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Institution\ValueObject;

final readonly class DigitalArchiveFile
{
    /** @param list<array{orderId: string, orderNumber: string, lineId: string, kind: string, childCode: string}> $sources */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $photoId,
        public string $filename,
        public string $entryName,
        public ?string $relativePath,
        public ?int $bytes,
        public array $sources,
    ) {}
}
