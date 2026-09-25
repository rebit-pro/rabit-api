<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;

interface GalleryQuestionContextInterface
{
    /** Недействительная или отозванная ссылка — 404 GALLERY_NOT_FOUND, как в MED-01. */
    public function resolve(string $galleryToken): GalleryQuestionContextDto;
}
