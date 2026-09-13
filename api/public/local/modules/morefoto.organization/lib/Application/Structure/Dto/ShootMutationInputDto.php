<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Morefoto\Organization\Domain\Structure\ValueObject\ShootDate;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;

final readonly class ShootMutationInputDto
{
    public ?string $name;

    public function __construct(public string $key, ?string $name, public bool $dateProvided, public ?string $date, public ?int $revision)
    {
        $this->name = null === $name ? null : (new StructureName($name))->value;
        new ShootDate($date);
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $key) || (null !== $revision && (1 > $revision || 2147483646 < $revision))) {
            throw new \InvalidArgumentException('Invalid operation key or revision.');
        }
    }

    public function payloadHash(): string
    {
        return hash('sha256', json_encode([$this->name, $this->dateProvided, $this->date, $this->revision], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
