<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchivePublisherInterface;
use Morefoto\Files\Application\Institution\Dto\DigitalArchiveOutputDto;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveSnapshot;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;
use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Сохраняет полный состав служебной выгрузки до публикации сообщения; повтор ключа возвращает прежний снимок. */
final readonly class CreateInstitutionDigitalArchiveUseCase
{
    public function __construct(
        private DigitalArchiveAccess $access,
        private DigitalArchiveRepositoryInterface $archives,
        private DigitalArchiveSnapshot $snapshot,
        private DigitalArchiveView $view,
        private DownloadIdGeneratorInterface $ids,
        private DigitalArchivePublisherInterface $publisher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $actorId, string $institutionId, string $key): DigitalArchiveOutputDto
    {
        $institution = $this->access->institution($actorId, $institutionId, true);
        if (1 !== preg_match('/^[A-Za-z0-9._-]{16,128}$/D', $key)) {
            throw new HttpException('IDEMPOTENCY_KEY_REQUIRED', 422);
        }
        $hash = hash('sha256', $key);
        try {
            $archive = $this->archives->exclusive('request:' . $actorId . ':' . $hash, function() use ($actorId, $institution, $hash): DigitalArchive {
                $previous = $this->archives->request($actorId, $hash);
                if (null !== $previous) {
                    if ($previous->institutionPublicId !== $institution->id || $previous->institutionId !== $institution->nativeId) {
                        throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                    }

                    return $previous;
                }

                return $this->archives->exclusive('institution:' . $institution->id, function() use ($actorId, $institution, $hash): DigitalArchive {
                    $pending = $this->archives->pending($institution->nativeId);
                    if (null !== $pending) {
                        throw new HttpException('DIGITAL_ARCHIVE_IN_PROGRESS', 409, null, ['archiveId' => $pending->id]);
                    }
                    $now = $this->clock->now();
                    $files = $this->snapshot->capture($institution->nativeId, $institution->id, $now);
                    $this->access->institution($actorId, $institution->id, true);
                    $created = new DigitalArchive(
                        id: $this->ids->uuid(),
                        institutionId: $institution->nativeId,
                        institutionPublicId: $institution->id,
                        actorId: $actorId,
                        keyHash: $hash,
                        capturedAt: $now,
                        files: $files,
                        status: [] === $files ? 'empty' : 'pending',
                    );
                    $this->archives->insert($created);

                    return $created;
                });
            });
        } catch (DigitalArchiveException $error) {
            throw new HttpException($error->getMessage(), 'DIGITAL_ARCHIVE_TOO_LARGE' === $error->getMessage() ? 413 : 409, $error);
        }
        if ('pending' === $archive->status) {
            try {
                $this->publisher->build($archive->id, $archive->attempts);
            } catch (\Throwable $error) {
                $this->logger->warning('Digital archive waits for dispatch.', ['archiveId' => $archive->id, 'exception' => $error::class]);
            }
        }

        return $this->view->describe($archive);
    }
}
