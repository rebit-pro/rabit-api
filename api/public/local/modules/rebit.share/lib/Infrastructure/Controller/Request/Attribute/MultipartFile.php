<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

/** Предметные коды отказа для единственного файла multipart-поля `file`. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class MultipartFile
{
    public function __construct(
        public string $missingCode,
        public string $failedCode,
    ) {}
}
