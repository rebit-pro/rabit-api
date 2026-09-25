<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class AskGalleryQuestionInputDto
{
    public function __construct(
        public string $galleryToken,
        public string $name,
        public string $message,
    ) {}
}
