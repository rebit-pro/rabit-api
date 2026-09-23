<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Profile\Dto;

use Morefoto\Access\Application\Avatar\Dto\AvatarOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ProfileOutputDto implements ResultDtoInterface
{
    /** @param list<string> $permissions */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $active,
        public int $accessRevision,
        public array $permissions,
        public ?AvatarOutputDto $avatar = null,
    ) {}
}
