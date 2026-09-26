<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\ValueObject;

/** Принятый ключ идемпотентности: тело запроса, за которым он закреплён, и загрузка, которую он вернул. */
final readonly class DownloadRequest
{
    public function __construct(
        public string $requestHash,
        public string $downloadId,
    ) {}
}
