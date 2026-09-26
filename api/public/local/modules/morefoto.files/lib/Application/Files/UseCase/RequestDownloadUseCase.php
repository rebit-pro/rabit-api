<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\FilesPublisherInterface;
use Morefoto\Files\Application\Files\Contract\OrderDownloadGuardInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Dto\DownloadOutputDto;
use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Application\Files\Dto\RequestDownloadInputDto;
use Morefoto\Files\Application\Files\Service\DownloadView;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Exception\DuplicateDownloadException;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Domain\Download\ValueObject\Download;
use Morefoto\Files\Domain\Download\ValueObject\DownloadRequest;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** FIL-02: принимает запрос покупателя на одиночный оригинал или архив из разрешённых файлов оплаченного заказа.
 * Одиночный файл готов сразу; архив ставится в фоновую сборку, причём у заказа одновременно собирается только один ZIP,
 * а готовый архив того же состава переиспользуется. Каждый принятый ключ идемпотентности закрепляется за телом запроса
 * и выданной загрузкой, поэтому повтор возвращает её же, даже если с тех пор изменился состав комплекта.
 */
final readonly class RequestDownloadUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private DownloadRepositoryInterface $downloads,
        private OrderDownloadGuardInterface $guard,
        private ProtectedStorageInterface $storage,
        private FileAccessPolicy $policy,
        private DownloadView $view,
        private DownloadIdGeneratorInterface $ids,
        private FilesPublisherInterface $publisher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(?string $orderKey, RequestDownloadInputDto $input): DownloadOutputDto
    {
        $access = $this->access->byKey($orderKey);
        if (FilesStateEnum::AVAILABLE !== $access->state || null === $access->availableUntil) {
            throw new HttpException('FILES_UNAVAILABLE', 409, null, ['state' => $access->state->value]);
        }
        $now = $this->clock->now();
        $idempotencyHash = hash('sha256', $input->idempotencyKey);
        $requestHash = hash('sha256', $input->kind->value . '|' . (null === $input->photoIds ? '*' : implode(',', $input->photoIds)));
        /** @var array{0: Download, 1: bool} $outcome */
        $outcome = $this->guard->atomically($access->orderId, function() use ($access, $input, $idempotencyHash, $requestHash, $now): array {
            $known = $this->downloads->request($access->orderId, $idempotencyHash);
            if (null !== $known) {
                if ($known->requestHash !== $requestHash) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }
                $download = $this->downloads->find($known->downloadId) ?? throw new HttpException('DOWNLOAD_NOT_FOUND', 404);

                return [$download, false];
            }
            [$download, $created] = $this->choose($access, $input, $now);
            $this->downloads->rememberRequest($access->orderId, $idempotencyHash, new DownloadRequest($requestHash, $download->publicId), $now);

            return [$download, $created];
        });
        [$download, $created] = $outcome;
        if ($created && DownloadKindEnum::ZIP === $download->kind) {
            try {
                $this->publisher->build($download->publicId, 0);
            } catch (\Throwable $error) {
                // The pending row is durable: app:files:dispatch-pending republishes it after the retry pause.
                $this->logger->warning('Archive build was not queued.', ['downloadId' => $download->publicId, 'exception' => $error::class]);
            }
        }

        return $this->view->output($download, $access, $now);
    }

    /** @return array{0: Download, 1: bool} загрузка и признак, что она создана этим запросом */
    private function choose(FileAccessOutputDto $access, RequestDownloadInputDto $input, \DateTimeImmutable $now): array
    {
        $photoIds = $this->composition($input, $access);
        $compositionHash = hash('sha256', implode(',', $photoIds));
        if (DownloadKindEnum::ZIP === $input->kind) {
            $bytes = 0;
            foreach ($photoIds as $photoId) {
                $bytes += $access->files[$photoId]->bytes;
            }
            $this->policy->assertArchiveFits(count($photoIds), $bytes);
            $existing = $this->downloads->reusableArchive($access->orderId, $compositionHash, $now) ?? $this->pending($access->orderId, $compositionHash);
            if (null !== $existing) {
                return [$existing, false];
            }
        }
        $download = $this->download($access, $input->kind, $photoIds, $compositionHash, $now);
        try {
            $this->downloads->insert($download, $now);
        } catch (DuplicateDownloadException) {
            throw new HttpException('ARCHIVE_IN_PROGRESS', 409);
        }

        return [$download, true];
    }

    /** Та же сборка возвращается; сборка другого состава блокирует новую до завершения. */
    private function pending(int $orderId, string $compositionHash): ?Download
    {
        $pending = $this->downloads->pendingArchive($orderId);
        if (null !== $pending && $pending->compositionHash !== $compositionHash) {
            throw new HttpException('ARCHIVE_IN_PROGRESS', 409, null, ['downloadId' => $pending->publicId]);
        }

        return $pending;
    }

    /** @return non-empty-list<string> разрешённые кадры в порядке списка файлов заказа */
    private function composition(RequestDownloadInputDto $input, FileAccessOutputDto $access): array
    {
        $requested = $input->photoIds;
        if (DownloadKindEnum::FILE === $input->kind && (null === $requested || 1 !== count($requested))) {
            throw new HttpException('INVALID_DOWNLOAD', 422, null, ['photoIds' => 'A file download needs exactly one photo.']);
        }
        if (null === $requested) {
            return array_map('strval', array_keys($access->files));
        }
        if ([] === $requested || count($requested) !== count(array_unique($requested))) {
            throw new HttpException('INVALID_DOWNLOAD', 422, null, ['photoIds' => 'Photos must be a non-empty list without repeats.']);
        }
        $selected = array_fill_keys($requested, true);
        foreach ($requested as $photoId) {
            if (!isset($access->files[$photoId])) {
                throw new HttpException('PHOTO_NOT_ENTITLED', 422, null, ['photoId' => $photoId]);
            }
        }

        return array_values(array_filter(array_map('strval', array_keys($access->files)), static fn(string $id): bool => isset($selected[$id])));
    }

    /** @param non-empty-list<string> $photoIds */
    private function download(FileAccessOutputDto $access, DownloadKindEnum $kind, array $photoIds, string $compositionHash, \DateTimeImmutable $now): Download
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '_', $access->orderNumber) ?: 'order';
        $file = DownloadKindEnum::FILE === $kind ? $access->files[$photoIds[0]] : null;
        $id = $this->ids->uuid();

        return new Download(
            id: 0,
            publicId: $id,
            orderId: $access->orderId,
            kind: $kind,
            status: null === $file ? DownloadStatusEnum::PENDING : DownloadStatusEnum::READY,
            photoIds: $photoIds,
            compositionHash: $compositionHash,
            filename: 'morefoto-' . $number . (null === $file ? '.zip' : '-' . $file->filename),
            archivePath: null === $file ? $this->storage->archivePath($access->orderPublicId, $id) : null,
            bytes: $file?->bytes,
            errorCode: null,
            attempts: 0,
            nextAttemptAt: null === $file ? $this->policy->retryAt($now) : null,
            expiresAt: null === $file || null === $access->availableUntil ? null : $this->policy->downloadExpiresAt($now, $access->availableUntil),
        );
    }
}
