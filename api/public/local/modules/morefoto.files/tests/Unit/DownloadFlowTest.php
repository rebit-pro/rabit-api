<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\FilesPublisherInterface;
use Morefoto\Files\Application\Files\Dto\RequestDownloadInputDto;
use Morefoto\Files\Application\Files\Message\BuildArchiveMessage;
use Morefoto\Files\Application\Files\Message\Handler\BuildArchiveMessageHandler;
use Morefoto\Files\Application\Files\Service\DownloadView;
use Morefoto\Files\Application\Files\UseCase\DispatchPendingDownloadsUseCase;
use Morefoto\Files\Application\Files\UseCase\GetDownloadUseCase;
use Morefoto\Files\Application\Files\UseCase\OpenDownloadContentUseCase;
use Morefoto\Files\Application\Files\UseCase\PurgeDownloadsUseCase;
use Morefoto\Files\Application\Files\UseCase\RequestDownloadUseCase;
use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Infrastructure\File\LocalProtectedStorage;
use Morefoto\Files\Infrastructure\File\ZipArchiveBuilder;
use Morefoto\Files\Infrastructure\Security\HmacDownloadToken;
use Morefoto\Files\Tests\Unit\Support\FilesFixture;
use Morefoto\Files\Tests\Unit\Support\InMemoryDownloads;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J1-T05…T08, T11: запрос, идемпотентность, фоновая сборка настоящего ZIP, повторная проверка при выдаче и уборка.
 *
 * @internal
 */
final class DownloadFlowTest extends TestCase
{
    private FilesFixture $fixture;
    private InMemoryDownloads $downloads;
    private HmacDownloadToken $tokens;
    private string $root;

    /** @var list<array{0: string, 1: int}> */
    private array $published = [];
    private bool $brokerDown = false;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/j1-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/media', 0700, true);
        $this->fixture = new FilesFixture();
        foreach ([FilesFixture::P1 => 'first', FilesFixture::P2 => 'second', FilesFixture::P3 => 'third'] as $id => $content) {
            $path = $this->root . '/media/' . $id . '.jpg';
            file_put_contents($path, str_repeat($content, 100));
            $this->fixture->paths[$id] = $path;
        }
        $this->downloads = new InMemoryDownloads();
        $this->tokens = new HmacDownloadToken(str_repeat('s', 32));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testSingleFileIsReadyAtOnceAndOpensAsTheOriginal(): void
    {
        $download = $this->request(DownloadKindEnum::FILE, [FilesFixture::P2]);

        self::assertSame(['file', 'ready', 'morefoto-MF-0007-CD001.jpg'], [$download->kind, $download->status, $download->filename]);
        self::assertSame('2027-02-11 09:00:00', $download->expiresAt?->format('Y-m-d H:i:s'));
        self::assertNotNull($download->contentToken);
        $file = $this->content()->execute($download->id, null, $download->contentToken);
        self::assertSame('/_protected/media/shoot/22/' . FilesFixture::P2 . '.jpg', $file->internalUri);
        self::assertSame(['morefoto-MF-0007-CD001.jpg', 'image/jpeg', 600], [$file->filename, $file->mimeType, $file->bytes]);
        self::assertSame([], $this->published);
    }

    public function testDownloadNeverOutlivesTheFilesMonth(): void
    {
        $this->fixture->now = '2027-02-28 01:00:00';

        self::assertSame('2027-02-28 07:00:00', $this->request(DownloadKindEnum::FILE, [FilesFixture::P1])->expiresAt?->format('Y-m-d H:i:s'));
    }

    public function testZipIsBuiltInTheBackgroundWithTheExactOriginals(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, null);
        self::assertSame(['pending', null], [$pending->status, $pending->contentToken]);
        self::assertSame([[$pending->id, 0]], $this->published);

        $this->handler()(new BuildArchiveMessage($pending->id, 0));
        $ready = $this->get($pending->id);

        self::assertSame(['ready', 'morefoto-MF-0007.zip'], [$ready->status, $ready->filename]);
        $file = $this->content()->execute($ready->id, FilesFixture::KEY, null);
        self::assertSame('/_protected/files/archives/' . FilesFixture::ORDER . '/' . $ready->id . '.zip', $file->internalUri);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->root . '/files/archives/' . FilesFixture::ORDER . '/' . $ready->id . '.zip'));
        self::assertSame(3, $zip->numFiles);
        self::assertSame(str_repeat('first', 100), $zip->getFromName('AB001.jpg'));
        self::assertSame(str_repeat('second', 100), $zip->getFromName('CD001.jpg'));
        self::assertSame(str_repeat('third', 100), $zip->getFromName('CD002.jpg'));
        self::assertSame(\ZipArchive::CM_STORE, $zip->statName('CD002.jpg')['comp_method']);
        $zip->close();
    }

    public function testDuplicateMessageDoesNotBuildTwice(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, [FilesFixture::P1]);
        $this->handler()(new BuildArchiveMessage($pending->id, 0));
        $this->handler()(new BuildArchiveMessage($pending->id, 0));

        self::assertSame(1, $this->downloads->find($pending->id)?->attempts);
    }

    public function testIdempotencyReplaysAndRejectsAnotherBody(): void
    {
        $first = $this->request(DownloadKindEnum::FILE, [FilesFixture::P1], 'k1');
        self::assertSame($first->id, $this->request(DownloadKindEnum::FILE, [FilesFixture::P1], 'k1')->id);

        $this->expectRefusal('IDEMPOTENCY_CONFLICT', 409, fn() => $this->request(DownloadKindEnum::FILE, [FilesFixture::P2], 'k1'));
    }

    public function testOneArchiveBuildPerOrder(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, null, 'k1');
        self::assertSame($pending->id, $this->request(DownloadKindEnum::ZIP, null, 'k2')->id);

        $this->expectRefusal('ARCHIVE_IN_PROGRESS', 409, fn() => $this->request(DownloadKindEnum::ZIP, [FilesFixture::P1], 'k3'));
    }

    public function testReadyArchiveOfTheSameCompositionIsReused(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, [FilesFixture::P2, FilesFixture::P1], 'k1');
        $this->handler()(new BuildArchiveMessage($pending->id, 0));

        // The subset is stored in the order of the files list, so the same photos in another order reuse the archive.
        self::assertSame($pending->id, $this->request(DownloadKindEnum::ZIP, [FilesFixture::P1, FilesFixture::P2], 'k2')->id);
        self::assertCount(1, $this->published);
    }

    public function testRequestValidation(): void
    {
        $this->expectRefusal('INVALID_DOWNLOAD', 422, fn() => $this->request(DownloadKindEnum::FILE, null));
        $this->expectRefusal('INVALID_DOWNLOAD', 422, fn() => $this->request(DownloadKindEnum::FILE, [FilesFixture::P1, FilesFixture::P2]));
        $this->expectRefusal('INVALID_DOWNLOAD', 422, fn() => $this->request(DownloadKindEnum::ZIP, [FilesFixture::P1, FilesFixture::P1]));
        $this->expectRefusal('PHOTO_NOT_ENTITLED', 422, fn() => $this->request(DownloadKindEnum::FILE, ['44444444-4444-4444-8444-444444444444']));
        $this->fixture->paymentStatus = 'unpaid';
        $this->expectRefusal('FILES_UNAVAILABLE', 409, fn() => $this->request(DownloadKindEnum::FILE, [FilesFixture::P1]));
    }

    public function testBrokerOutageKeepsTheBuildAndDispatchRecoversIt(): void
    {
        $this->brokerDown = true;
        $pending = $this->request(DownloadKindEnum::ZIP, null);
        self::assertSame('pending', $pending->status);
        $this->brokerDown = false;

        self::assertSame(0, $this->dispatch()->execute(10)->processed);
        $this->fixture->now = '2027-02-10 09:01:00';
        self::assertSame(1, $this->dispatch()->execute(10)->processed);
        self::assertSame([[$pending->id, 0]], $this->published);
    }

    public function testBuildFailureRetriesThenFailsWithoutBlockingTheOrder(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, null);
        unlink($this->fixture->paths[FilesFixture::P3]);
        // The original vanished from disk while Media still lists it: a technical failure, retried three times.
        foreach (['09:00:00', '09:01:00', '09:02:00'] as $time) {
            $this->fixture->now = '2027-02-10 ' . $time;
            $this->handler()(new BuildArchiveMessage($pending->id, 0));
        }
        $failed = $this->get($pending->id);

        self::assertSame(['failed', 'ARCHIVE_FAILED', null], [$failed->status, $failed->error, $failed->contentToken]);
        self::assertSame([], glob($this->root . '/files/archives/*/*') ?: []);
        self::assertSame('pending', $this->request(DownloadKindEnum::ZIP, [FilesFixture::P1], 'retry')->status);
    }

    public function testContentRechecksRightTokenAndComposition(): void
    {
        $download = $this->request(DownloadKindEnum::FILE, [FilesFixture::P2]);
        $this->expectRefusal('INVALID_DOWNLOAD_TOKEN', 403, fn() => $this->content()->execute($download->id, null, null));
        $this->expectRefusal('INVALID_DOWNLOAD_TOKEN', 403, fn() => $this->content()->execute(FilesFixture::P3, null, $download->contentToken));
        $this->expectRefusal('ORDER_NOT_FOUND', 404, fn() => $this->content()->execute($download->id, str_repeat('c', 64), null));

        $this->fixture->now = '2027-02-10 09:10:00';
        $this->expectRefusal('INVALID_DOWNLOAD_TOKEN', 403, fn() => $this->content()->execute($download->id, null, $download->contentToken));

        unset($this->fixture->paths[FilesFixture::P2]);
        $this->expectRefusal('COMPOSITION_CHANGED', 409, fn() => $this->content()->execute($download->id, FilesFixture::KEY, null));

        $this->fixture->paths[FilesFixture::P2] = $this->root . '/media/' . FilesFixture::P2 . '.jpg';
        $this->fixture->now = '2027-02-28 07:00:00';
        $this->expectRefusal('FILES_EXPIRED', 410, fn() => $this->content()->execute($download->id, FilesFixture::KEY, null));
        self::assertSame(['expired', null], [$this->get($download->id, FilesFixture::KEY)->status, $this->get($download->id, FilesFixture::KEY)->contentToken]);
    }

    public function testPendingAndForeignDownloadsAreNotOpened(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, null);
        $this->expectRefusal('DOWNLOAD_NOT_READY', 409, fn() => $this->content()->execute($pending->id, FilesFixture::KEY, null));
        $this->expectRefusal('DOWNLOAD_NOT_FOUND', 404, fn() => $this->get('55555555-5555-4555-8555-555555555555'));
    }

    public function testPurgeRemovesExpiredArchives(): void
    {
        $pending = $this->request(DownloadKindEnum::ZIP, null);
        $this->handler()(new BuildArchiveMessage($pending->id, 0));
        $archive = $this->root . '/files/archives/' . FilesFixture::ORDER . '/' . $pending->id . '.zip';
        self::assertFileExists($archive);

        $this->fixture->now = '2027-02-11 09:00:00';
        self::assertSame(1, $this->purge()->execute(10)->processed);

        self::assertFileDoesNotExist($archive);
        self::assertSame('expired', $this->downloads->find($pending->id)?->status->value);
    }

    /** @param null|list<string> $photoIds */
    private function request(DownloadKindEnum $kind, ?array $photoIds, string $key = 'default')
    {
        return new RequestDownloadUseCase(
            $this->fixture->access(),
            $this->downloads,
            new FileAccessPolicy(),
            $this->view(),
            $this->idGenerator(),
            $this->publisher(),
            $this->fixture->clock(),
            new NullLogger(),
        )->execute(FilesFixture::KEY, new RequestDownloadInputDto($kind, $photoIds, md5($key)));
    }

    private function get(string $downloadId, string $key = FilesFixture::KEY)
    {
        return new GetDownloadUseCase($this->fixture->access(), $this->downloads, $this->view(), $this->fixture->clock())->execute($key, $downloadId);
    }

    private function content(): OpenDownloadContentUseCase
    {
        return new OpenDownloadContentUseCase($this->fixture->access(), $this->downloads, $this->tokens, $this->storage(), $this->fixture->clock());
    }

    private function handler(): BuildArchiveMessageHandler
    {
        return new BuildArchiveMessageHandler(
            $this->downloads,
            $this->fixture->access(),
            new ZipArchiveBuilder(),
            $this->storage(),
            new FileAccessPolicy(),
            $this->fixture->clock(),
            new NullLogger(),
        );
    }

    private function dispatch(): DispatchPendingDownloadsUseCase
    {
        return new DispatchPendingDownloadsUseCase($this->downloads, $this->publisher(), new FileAccessPolicy(), $this->fixture->clock(), new NullLogger());
    }

    private function purge(): PurgeDownloadsUseCase
    {
        return new PurgeDownloadsUseCase($this->downloads, $this->storage(), $this->fixture->clock(), new NullLogger());
    }

    private function view(): DownloadView
    {
        return new DownloadView(new FileAccessPolicy(), $this->tokens);
    }

    private function storage(): LocalProtectedStorage
    {
        return new LocalProtectedStorage($this->root . '/files');
    }

    private function idGenerator(): DownloadIdGeneratorInterface
    {
        $next = function(): string {
            return sprintf('%08x-0000-4000-8000-%012x', ++$this->ids, $this->ids);
        };

        return new class($next) implements DownloadIdGeneratorInterface {
            public function __construct(private readonly \Closure $next) {}

            public function uuid(): string
            {
                return ($this->next)();
            }
        };
    }

    private function publisher(): FilesPublisherInterface
    {
        $record = function(string $id, int $attempt): void {
            if ($this->brokerDown) {
                throw new \RuntimeException('broker down');
            }
            $this->published[] = [$id, $attempt];
        };

        return new class($record) implements FilesPublisherInterface {
            public function __construct(private readonly \Closure $record) {}

            public function build(string $downloadId, int $attempt): void
            {
                ($this->record)($downloadId, $attempt);
            }
        };
    }

    private function expectRefusal(string $code, int $status, callable $action): void
    {
        try {
            $action();
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame([$code, $status], [$error->getMessage(), $error->getCode()]);
        }
    }
}
