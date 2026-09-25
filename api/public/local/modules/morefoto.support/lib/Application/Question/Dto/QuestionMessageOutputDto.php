<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class QuestionMessageOutputDto
{
    public function __construct(
        public int $id,
        public string $author,
        public string $authorName,
        public string $text,
        public string $createdAt,
        public ?string $deliveryStatus,
    ) {}
}
