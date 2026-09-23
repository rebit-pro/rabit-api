<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Message\Handler;

use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Psr\Log\LoggerInterface;

/**
 * Готовит защищённые превью принятого кадра и фиксирует попытку, если подготовка не удалась.
 *
 * Пишет в журнал ожидание от приёма до начала обработки и длительность декодирования и обоих размеров превью.
 */
final readonly class ProcessPhotoMessageHandler
{
    public function __construct(
        private PhotoRepository $photos,
        private PrivatePhotoStorageInterface $storage,
        private PreviewRendererInterface $renderer,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessPhotoMessage $message): void
    {
        $job = $this->photos->processingJob($message->photoId);
        if (null === $job || 'processing' !== (string)$job['UF_STATUS']) {
            return;
        }
        $attempt = (int)$job['UF_ATTEMPTS'] + 1;
        try {
            $path = $job['UF_ORIGINAL_PATH'];
            if (!is_string($path) || '' === $path) {
                throw new \RuntimeException('Private original is unavailable.');
            }
            $preview = $this->renderer->render(
                $this->storage->absolutePath($path),
                (string)$job['UF_MIME_TYPE'],
                $message->photoId,
            );
            $this->photos->markReady($message->photoId, $preview);
        } catch (\Throwable $error) {
            $this->logger->warning('Photo preview preparation failed.', [
                'photoId' => $message->photoId,
                'attempt' => $attempt,
                'exception' => $error::class,
            ]);
            $this->photos->markAttemptFailed($message->photoId);
            throw $error;
        }
        $this->logger->info('Photo previews ready.', [
            'photoId' => $message->photoId,
            'attempt' => $attempt,
            'megapixels' => round((int)$job['UF_WIDTH'] * (int)$job['UF_HEIGHT'] / 1_000_000, 1),
            'sinceAcceptedSeconds' => (int)$job['CREATED_SECONDS'],
            'sinceQueuedSeconds' => (int)$job['UPDATED_SECONDS'],
            'decodeMs' => $preview->decodeMs,
            'thumbMs' => $preview->thumbMs,
            'previewMs' => $preview->previewMs,
        ]);
    }
}
