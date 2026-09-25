<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class QuestionMessageResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $id,
        public string $author,
        public string $authorName,
        public string $text,
        public string $createdAt,
        public ?string $delivery,
    ) {}
}
