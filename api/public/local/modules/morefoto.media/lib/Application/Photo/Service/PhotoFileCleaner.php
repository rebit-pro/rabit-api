<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Application\Photo\Contract\OriginalFileLockInterface;
use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Psr\Log\LoggerInterface;

/**
 * Стирает с диска оригинал и превью уже удалённого из базы кадра. Оригинал, который по-прежнему нужен другому кадру
 * с тем же содержимым, остаётся; ошибка стирания только журналируется, потому что оставшийся файл не возвращает кадр.
 */
final readonly class PhotoFileCleaner
{
    public function __construct(
        private PhotoRepository $photos,
        private PrivatePhotoStorageInterface $storage,
        private OriginalFileLockInterface $originals,
        private PreviewRendererInterface $previews,
        private LoggerInterface $logger,
    ) {}

    /** Each file is removed on its own: previews written by the media worker may refuse deletion, the private original must still go. */
    public function remove(string $photoId, ?string $originalPath): void
    {
        if (null !== $originalPath) {
            $this->removeLogged($photoId, 'original', function() use ($originalPath): void {
                // An upload of the same content reuses this path: it either registers first and keeps the file, or stores it anew after us.
                $this->originals->synchronized($originalPath, function() use ($originalPath): void {
                    if (!$this->photos->originalPathInUse($originalPath)) {
                        $this->storage->delete($originalPath);
                    }
                });
            });
        }
        $this->removeLogged($photoId, 'previews', fn() => $this->previews->remove($photoId));
    }

    private function removeLogged(string $photoId, string $files, \Closure $removal): void
    {
        try {
            $removal();
        } catch (\Throwable $error) {
            $this->logger->warning('Deleted photo files remain on disk.', [
                'photoId' => $photoId,
                'files' => $files,
                'exception' => $error::class,
            ]);
        }
    }
}
