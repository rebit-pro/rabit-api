<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\UseCase;

use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Отдаёт только производную снимка после проверки ключа и принадлежности назначения группе.
 * Каждое чтение повторно проверяет отзыв ключа; оригинал недоступен через этот сценарий.
 */
final readonly class GetGalleryPreviewUseCase
{
    public function __construct(
        private GalleryAccessInterface $gallery,
        private GalleryPhotoRepository $photos,
        private PreviewContentInterface $content,
    ) {}

    public function execute(string $token, string $assignmentId, string $variant): PreviewContentOutputDto
    {
        $context = $this->gallery->context($token);
        $group = $context->group;
        if ('preparing' === $context->state) {
            throw new HttpException('GALLERY_NOT_READY', 409);
        }
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $assignmentId)) {
            throw new HttpException('PHOTO_NOT_FOUND', 404);
        }
        $photoId = $this->photos->photoId($group->id, $group->shootId, $assignmentId);
        if (null === $photoId) {
            throw new HttpException('PHOTO_NOT_FOUND', 404);
        }

        return $this->content->read($photoId, $variant);
    }
}
