<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Dto\DispatchPendingOutputDto;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Psr\Log\LoggerInterface;

/**
 * Повторно ставит в очередь подготовку превью для кадров, чья немедленная публикация не состоялась.
 *
 * Берёт только задания старше окна дедупликации publisher, чтобы не принять пропуск дубля за отправку, и изолирует
 * сбой отдельного кадра: остальные задания прохода публикуются.
 */
final readonly class DispatchPendingPhotoJobsUseCase
{
    /** Longer than the 30-second publisher deduplication window. */
    private const int MIN_PENDING_SECONDS = 45;

    public function __construct(
        private PhotoRepository $photos,
        private MediaPublisherInterface $publisher,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $limit): DispatchPendingOutputDto
    {
        $published = 0;
        $failed = 0;
        $result = $this->photos->pendingJobs($limit, self::MIN_PENDING_SECONDS);
        while (false !== ($row = $result->fetch())) {
            $photoId = (string)$row['UF_PUBLIC_ID'];
            $revision = (int)$row['UF_REVISION'];
            try {
                $this->publisher->process($photoId, $revision);
                $this->photos->markPublished($photoId);
                ++$published;
                $this->logger->info('Pending photo job dispatched.', [
                    'photoId' => $photoId,
                    'revision' => $revision,
                    'pendingSeconds' => (int)$row['PENDING_SECONDS'],
                ]);
            } catch (\Throwable $error) {
                ++$failed;
                $this->logger->warning('Pending photo job was not dispatched.', [
                    'photoId' => $photoId,
                    'revision' => $revision,
                    'exception' => $error::class,
                    'previous' => null === $error->getPrevious() ? null : $error->getPrevious()::class,
                ]);
            }
        }

        return new DispatchPendingOutputDto(published: $published, failed: $failed);
    }
}
