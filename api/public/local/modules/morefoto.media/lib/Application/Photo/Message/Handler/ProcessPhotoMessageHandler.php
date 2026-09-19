<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Message\Handler;

use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;

final readonly class ProcessPhotoMessageHandler
{
    public function __construct(
        private PhotoRepository $photos,
        private PrivatePhotoStorageInterface $storage,
        private PreviewRendererInterface $renderer,
    ) {}

    public function __invoke(ProcessPhotoMessage $message): void
    {
        $row = $this->photos->photo($message->photoId)->fetch();
        if (!is_array($row) || 'processing' !== (string)$row['UF_STATUS']) {
            return;
        }
        try {
            $path = $row['UF_ORIGINAL_PATH'] ?? null;
            if (!is_string($path) || '' === $path) {
                throw new \RuntimeException('Private original is unavailable.');
            }
            $preview = $this->renderer->render(
                $this->storage->absolutePath($path),
                (string)$row['UF_MIME_TYPE'],
                $message->photoId,
            );
            $this->photos->markReady($message->photoId, $preview);
        } catch (\Throwable $error) {
            $this->photos->markAttemptFailed($message->photoId);
            throw $error;
        }
    }
}
