<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\ValueObject;

final readonly class StructureName
{
    public string $value;

    public function __construct(string $value)
    {
        if (!mb_check_encoding($value, 'UTF-8') || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException('Invalid structure name.');
        }
        $value = trim($value);
        if ('' === $value || 255 < mb_strlen($value)) {
            throw new \InvalidArgumentException('Name must contain 1–255 characters.');
        }
        $this->value = $value;
    }
}
