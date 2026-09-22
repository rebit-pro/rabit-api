<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupDirectoryItemOutputDto
{
    public function __construct(
        public int $nativeId,
        public string $id,
        public string $name,
        public string $kind,
        public int $institutionNativeId,
        public string $institutionId,
        public string $institutionName,
        public int $shootNativeId,
        public string $shootId,
        public string $shootName,
        public GroupCalendarOutputDto $calendar,
    ) {}
}
