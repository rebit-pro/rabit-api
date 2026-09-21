<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\UseCase;

use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;

/** Открывает приватную витрину по ключу с проверкой отзыва и фактических сроков группы. */
final readonly class GetGalleryUseCase
{
    public function __construct(private GalleryAccessInterface $access) {}

    public function execute(string $token): GalleryAccessOutputDto
    {
        return $this->access->resolve($token);
    }
}
