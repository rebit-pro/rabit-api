<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\UseCase;

use Morefoto\Media\Application\Gallery\Dto\GalleryViewOutputDto;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Gallery\ValueObject\GalleryAlias;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Открывает приватную витрину по ключу или по его короткому псевдониму (F3) с проверкой отзыва и фактических сроков
 * группы. Псевдоним сводится к ключу, чтобы корзина, заказы и вопросы работали по нему, как по длинной ссылке.
 */
final readonly class GetGalleryUseCase
{
    public function __construct(private GalleryAccessInterface $access, private GalleryCapabilityRepository $keys) {}

    public function execute(string $tokenOrAlias): GalleryViewOutputDto
    {
        $token = GalleryAlias::matches($tokenOrAlias)
            ? $this->keys->tokenByAlias($tokenOrAlias) ?? throw new HttpException('GALLERY_NOT_FOUND', 404)
            : $tokenOrAlias;

        return new GalleryViewOutputDto($token, $this->access->resolve($token));
    }
}
