<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Institution\ValueObject;

final readonly class DigitalArchive
{
    /**
     * @param list<DigitalArchiveFile> $files
     * @param list<string>             $workPaths все заранее сохранённые пути попыток, включая устаревших исполнителей
     */
    public function __construct(
        public string $id,
        public int $institutionId,
        public string $institutionPublicId,
        public int $actorId,
        public string $keyHash,
        public \DateTimeImmutable $capturedAt,
        public array $files,
        public string $status = 'pending',
        public int $attempts = 0,
        public ?string $owner = null,
        public array $workPaths = [],
        public ?string $archivePath = null,
        public ?int $bytes = null,
        public ?string $error = null,
        public ?\DateTimeImmutable $readyAt = null,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
