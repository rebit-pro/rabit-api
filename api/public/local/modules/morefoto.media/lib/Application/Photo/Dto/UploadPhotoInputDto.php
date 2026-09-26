<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class UploadPhotoInputDto
{
    /** @param list<string> $childCodes коды детей, к которым привязать кадр при приёме; пустой — без разметки */
    public function __construct(
        public string $shootId,
        public string $groupId,
        public string $tmpName,
        public string $filename,
        public int $bytes,
        public ?string $clientFingerprint,
        public array $childCodes = [],
    ) {}
}
