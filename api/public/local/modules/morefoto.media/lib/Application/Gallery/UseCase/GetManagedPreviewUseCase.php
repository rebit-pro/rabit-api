<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\UseCase;

use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Application\Photo\UseCase\GetPhotoUseCase;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет актуальные права сотрудника на снимок и читает защищённую производную для кабинета. */
final readonly class GetManagedPreviewUseCase
{
    public function __construct(private GetPhotoUseCase $photos, private PreviewContentInterface $content) {}

    public function execute(int $userId, string $photoId, string $variant): PreviewContentOutputDto
    {
        $photo = $this->photos->execute($userId, $photoId);
        if ('ready' !== $photo->status) {
            throw new HttpException('PREVIEW_UNAVAILABLE', 404);
        }

        return $this->content->read($photo->id, $variant);
    }
}
