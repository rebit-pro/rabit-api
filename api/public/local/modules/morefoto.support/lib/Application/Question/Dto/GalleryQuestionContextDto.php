<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class GalleryQuestionContextDto
{
    public function __construct(
        public int $groupId,
        public string $institutionName,
        public string $groupName,
        public ?string $curatorName,
    ) {}
}
