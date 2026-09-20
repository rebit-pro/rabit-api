<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;

final readonly class DispatchPendingPhotoJobsUseCase
{
    public function __construct(
        private PhotoRepository $photos,
        private MediaPublisherInterface $publisher,
    ) {}

    public function execute(int $limit): int
    {
        $published = 0;
        $result = $this->photos->pendingJobs($limit);
        while (false !== ($row = $result->fetch())) {
            $photoId = (string)$row['UF_PUBLIC_ID'];
            $this->publisher->process($photoId, (int)$row['UF_REVISION']);
            $this->photos->markPublished($photoId);
            ++$published;
        }

        return $published;
    }
}
