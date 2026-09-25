<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\PhotoRegistration;
use Morefoto\Media\Application\Photo\Dto\PreviewOutputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Application\Photo\Message\Handler\ProcessPhotoMessageHandler;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Application\Photo\UseCase\DispatchPendingPhotoJobsUseCase;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoInputDto;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PhotoPipelineDiagnosticsTest extends TestCase
{
    private const string PHOTO_ID = '12345678-abcd-4abc-8abc-123456789abc';
    private const string SECRET = 'amqp://guest:secret@rabbitmq:5672';
    private const array JOB = [
        'UF_STATUS' => 'processing',
        'UF_ORIGINAL_PATH' => '22345678-abcd-4abc-8abc-123456789abc/ab/photo.jpg',
        'UF_MIME_TYPE' => 'image/jpeg',
        'UF_WIDTH' => '6000',
        'UF_HEIGHT' => '4000',
        'UF_ATTEMPTS' => '0',
        'CREATED_SECONDS' => '58',
        'UPDATED_SECONDS' => '57',
    ];

    private ?string $file = null;

    protected function tearDown(): void
    {
        if (null !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testFailedImmediatePublishKeepsPendingAndLogsStageWithoutMessage(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('register')->willReturn(new PhotoRegistration(self::PHOTO_ID, 'processing', 1, true));
        $photos->expects(self::never())->method('markPublished');
        $publisher = $this->createStub(MediaPublisherInterface::class);
        $publisher->method('process')->willThrowException(new \RuntimeException(self::SECRET, 0, new \LogicException('inner')));
        $logger = new CollectingLogger();

        $result = $this->upload($photos, $publisher, $logger);

        self::assertSame('processing', $result->status);
        $warning = $logger->first('warning');
        self::assertSame('publish', $warning['stage']);
        self::assertSame(\RuntimeException::class, $warning['exception']);
        self::assertSame(\LogicException::class, $warning['previous']);
        self::assertFalse($logger->first('info')['published']);
        self::assertStringNotContainsString('secret', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    public function testFailedPublishedMarkerIsLoggedAsSeparateStage(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('register')->willReturn(new PhotoRegistration(self::PHOTO_ID, 'processing', 1, true));
        $photos->expects(self::once())->method('markPublished')->willThrowException(new \RuntimeException('db down'));
        $publisher = $this->createMock(MediaPublisherInterface::class);
        $publisher->expects(self::once())->method('process')->with(self::PHOTO_ID, 1);
        $logger = new CollectingLogger();

        $this->upload($photos, $publisher, $logger);

        self::assertSame('markPublished', $logger->first('warning')['stage']);
        $accepted = $logger->first('info');
        self::assertFalse($accepted['published']);
        foreach (['inspectMs', 'storeMs', 'registerMs', 'publishMs'] as $duration) {
            self::assertIsInt($accepted[$duration]);
        }
    }

    public function testDispatcherIsolatesFailedRowAndSkipsYoungJobs(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::once())->method('pendingJobs')->with(100, 45)->willReturn(new RowsResult([
            ['UF_PUBLIC_ID' => 'photo-1', 'UF_REVISION' => '1', 'PENDING_SECONDS' => '61'],
            ['UF_PUBLIC_ID' => 'photo-2', 'UF_REVISION' => '3', 'PENDING_SECONDS' => '62'],
            ['UF_PUBLIC_ID' => 'photo-3', 'UF_REVISION' => '1', 'PENDING_SECONDS' => '63'],
        ]));
        $published = [];
        $photos->method('markPublished')->willReturnCallback(static function(string $photoId) use (&$published): void {
            $published[] = $photoId;
        });
        $publisher = $this->createStub(MediaPublisherInterface::class);
        $publisher->method('process')->willReturnCallback(static function(string $photoId): void {
            if ('photo-2' === $photoId) {
                throw new \RuntimeException('broker unavailable');
            }
        });
        $logger = new CollectingLogger();

        $result = (new DispatchPendingPhotoJobsUseCase($photos, $publisher, $logger))->execute(100);

        self::assertSame(2, $result->published);
        self::assertSame(1, $result->failed);
        self::assertSame(['photo-1', 'photo-3'], $published);
        self::assertSame('photo-2', $logger->first('warning')['photoId']);
        self::assertSame(61, $logger->first('info')['pendingSeconds']);
    }

    public function testHandlerLogsQueueWaitAndRenderDurations(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('processingJob')->with(self::PHOTO_ID)->willReturn(self::JOB);
        $photos->expects(self::once())->method('markReady');
        $photos->expects(self::never())->method('markAttemptFailed');
        $renderer = $this->createStub(PreviewRendererInterface::class);
        $renderer->method('render')->willReturn(new PreviewOutputDto('/thumb.webp', '/preview.webp', 900, 40, 120));
        $logger = new CollectingLogger();

        $this->handler($photos, $renderer, $logger)(new ProcessPhotoMessage(self::PHOTO_ID, 2));

        $ready = $logger->first('info');
        self::assertSame(1, $ready['attempt']);
        self::assertSame(24.0, $ready['megapixels']);
        self::assertSame(58, $ready['sinceAcceptedSeconds']);
        self::assertSame(57, $ready['sinceQueuedSeconds']);
        self::assertSame([900, 40, 120], [$ready['decodeMs'], $ready['thumbMs'], $ready['previewMs']]);
    }

    public function testHandlerRecordsFailedAttemptAndRethrows(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('processingJob')->with(self::PHOTO_ID)->willReturn(self::JOB);
        $photos->expects(self::never())->method('markReady');
        $photos->expects(self::once())->method('markAttemptFailed')->with(self::PHOTO_ID);
        $renderer = $this->createStub(PreviewRendererInterface::class);
        $renderer->method('render')->willThrowException(new \RuntimeException('Cannot decode private original.'));
        $logger = new CollectingLogger();

        try {
            $this->handler($photos, $renderer, $logger)(new ProcessPhotoMessage(self::PHOTO_ID, 2));
            self::fail('Rendering failure must reach the messenger retry strategy.');
        } catch (\RuntimeException) {
        }

        $warning = $logger->first('warning');
        self::assertSame(1, $warning['attempt']);
        self::assertSame(\RuntimeException::class, $warning['exception']);
    }

    public function testDiagnosticRecordsSurviveTheCommonLogSanitizer(): void
    {
        $second = '52345678-abcd-4abc-8abc-123456789abc';
        $photos = $this->createStub(PhotoRepository::class);
        $photos->method('register')->willReturn(new PhotoRegistration(self::PHOTO_ID, 'processing', 1, true));
        $photos->method('pendingJobs')->willReturn(new RowsResult([
            ['UF_PUBLIC_ID' => self::PHOTO_ID, 'UF_REVISION' => '1', 'PENDING_SECONDS' => '61'],
            ['UF_PUBLIC_ID' => $second, 'UF_REVISION' => '2', 'PENDING_SECONDS' => '62'],
        ]));
        $photos->method('processingJob')->willReturn(self::JOB);
        $publisher = $this->createStub(MediaPublisherInterface::class);
        $publisher->method('process')->willReturnCallback(static function(string $photoId) use ($second): void {
            if ($second !== $photoId) {
                throw new \RuntimeException(self::SECRET, 0, new \LogicException('inner'));
            }
        });
        $renders = 0;
        $renderer = $this->createStub(PreviewRendererInterface::class);
        $renderer->method('render')->willReturnCallback(static function() use (&$renders): PreviewOutputDto {
            if (1 < ++$renders) {
                throw new \RuntimeException('Cannot decode private original.');
            }

            return new PreviewOutputDto('/thumb.webp', '/preview.webp', 900, 40, 120);
        });
        $logger = new CollectingLogger();

        $this->upload($photos, $publisher, $logger);
        (new DispatchPendingPhotoJobsUseCase($photos, $publisher, $logger))->execute(100);
        $handler = $this->handler($photos, $renderer, $logger);
        $handler(new ProcessPhotoMessage(self::PHOTO_ID, 2));
        try {
            $handler(new ProcessPhotoMessage(self::PHOTO_ID, 2));
        } catch (\RuntimeException) {
        }

        self::assertSame([
            'Photo job remains pending after immediate publish failure.',
            'Photo upload accepted.',
            'Pending photo job was not dispatched.',
            'Pending photo job dispatched.',
            'Photo previews ready.',
            'Photo preview preparation failed.',
        ], array_column($logger->records, 'message'));
        foreach ($logger->records as $record) {
            $sanitized = (new CommonLoggerProcessor(['message' => $record['message'], 'context' => $record['context'], 'extra' => []]))();
            $expected = array_filter($record['context'], static fn(mixed $value): bool => null !== $value);
            $actual = $sanitized['context'];
            ksort($expected);
            ksort($actual);

            self::assertSame($record['message'], $sanitized['message']);
            self::assertSame($expected, $actual, $record['message']);
        }
    }

    private function upload(
        PhotoRepository $photos,
        MediaPublisherInterface $publisher,
        CollectingLogger $logger,
    ): UploadPhotoOutputDto {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(
            institutionId: 1,
            shootId: 2,
            shootPublicId: '22345678-abcd-4abc-8abc-123456789abc',
            groupId: 3,
            groupPublicId: '32345678-abcd-4abc-8abc-123456789abc',
            groupEditable: true,
        ));
        $storage = $this->createStub(PrivatePhotoStorageInterface::class);
        $storage->method('store')->willReturn('22345678-abcd-4abc-8abc-123456789abc/ab/photo.png');
        $this->file = (string)tempnam(sys_get_temp_dir(), 'mf-photo');
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $this->file);

        return (new UploadPhotoUseCase(
            $scopes,
            $this->createStub(AccessGuardInterface::class),
            new PhotoFileInspector(),
            $storage,
            $photos,
            $publisher,
            $logger,
        ))->execute(4, new UploadPhotoInputDto(
            shootId: '22345678-abcd-4abc-8abc-123456789abc',
            groupId: '32345678-abcd-4abc-8abc-123456789abc',
            tmpName: $this->file,
            filename: 'photo.png',
            bytes: (int)filesize($this->file),
            clientFingerprint: null,
        ));
    }

    private function handler(
        PhotoRepository $photos,
        PreviewRendererInterface $renderer,
        CollectingLogger $logger,
    ): ProcessPhotoMessageHandler {
        $storage = $this->createStub(PrivatePhotoStorageInterface::class);
        $storage->method('absolutePath')->willReturn('/private/photo.jpg');

        return new ProcessPhotoMessageHandler($photos, $storage, $renderer, $logger);
    }
}

/** @internal */
final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string)$level, 'message' => (string)$message, 'context' => $context];
    }

    /** @return array<string, mixed> */
    public function first(string $level): array
    {
        foreach ($this->records as $record) {
            if ($level === $record['level']) {
                return $record['context'];
            }
        }
        throw new \LogicException('No ' . $level . ' record.');
    }
}

/** @internal */
final class RowsResult extends Result
{
    /** @param list<array<string, string>> $rows */
    public function __construct(private array $rows) {}

    /** @return array<string, string>|false */
    public function fetch(): array|false
    {
        return array_shift($this->rows) ?? false;
    }
}
