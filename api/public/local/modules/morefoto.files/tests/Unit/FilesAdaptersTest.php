<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Morefoto\Files\Infrastructure\File\LocalProtectedStorage;
use Morefoto\Files\Infrastructure\Security\HmacDownloadToken;
use Morefoto\Files\Presentation\Files\FilesInputMapper;
use Morefoto\Files\Presentation\Files\Request\Dto\CreateDownloadRequestDto;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J1-T08: подпись ссылки, пути internal-location и разбор запроса FIL-02.
 *
 * @internal
 */
final class FilesAdaptersTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    public function testTokenIsBoundToTheDownloadAndItsDeadline(): void
    {
        $tokens = new HmacDownloadToken(str_repeat('k', 32));
        $now = new \DateTimeImmutable('2027-02-10 09:00:00');
        $token = $tokens->issue(self::ID, $now->modify('+10 minutes'));

        self::assertTrue($tokens->valid(self::ID, $token, $now));
        self::assertFalse($tokens->valid(self::ID, $token, $now->modify('+10 minutes')));
        self::assertFalse($tokens->valid('22222222-2222-4222-8222-222222222222', $token, $now));
        self::assertFalse(new HmacDownloadToken(str_repeat('x', 32))->valid(self::ID, $token, $now));
        [$expires, $signature] = explode('.', $token);
        self::assertFalse($tokens->valid(self::ID, ((int)$expires + 3600) . '.' . $signature, $now));
        self::assertFalse($tokens->valid(self::ID, 'garbage', $now));
    }

    public function testTokenNeedsTheServerSecret(): void
    {
        $this->expectExceptionMessage('REBIT_ENCRYPTION_KEY');

        new HmacDownloadToken('short')->issue(self::ID, new \DateTimeImmutable());
    }

    public function testStorageBuildsOnlyInternalUrisInsideItsRoots(): void
    {
        $storage = new LocalProtectedStorage('/srv/files/');

        self::assertSame('archives/' . self::ID . '/' . self::ID . '.zip', $storage->archivePath(self::ID, self::ID));
        self::assertSame('/srv/files/archives/a/b.zip', $storage->absoluteArchivePath('archives/a/b.zip'));
        self::assertSame('/_protected/files/archives/a/b.zip', $storage->archiveUri('archives/a/b.zip'));
        self::assertSame('/_protected/media/shoot/ab/cd.jpg', $storage->originalUri('shoot/ab/cd.jpg'));
        foreach (['../etc/passwd', '/etc/passwd', 'a/../../b', 'a//b', 'a/b c', ''] as $path) {
            try {
                $storage->originalUri($path);
                self::fail('Accepted unsafe path ' . $path);
            } catch (FilesStorageException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(FilesStorageException::class);
        $storage->archivePath('../x', self::ID);
    }

    public function testCreateRequestMapping(): void
    {
        $mapper = new FilesInputMapper();
        $input = $mapper->create(new CreateDownloadRequestDto('zip', str_repeat('AB', 16), [self::ID]));

        self::assertSame(['zip', [self::ID], str_repeat('ab', 16)], [$input->kind->value, $input->photoIds, $input->idempotencyKey]);
        self::assertNull($mapper->create(new CreateDownloadRequestDto('zip', str_repeat('a', 32)))->photoIds);
        foreach ([
            ['INVALID_IDEMPOTENCY_KEY', new CreateDownloadRequestDto('zip', 'short')],
            ['INVALID_DOWNLOAD', new CreateDownloadRequestDto('rar', str_repeat('a', 32))],
            ['INVALID_DOWNLOAD', new CreateDownloadRequestDto('file', str_repeat('a', 32), [42])],
            ['INVALID_DOWNLOAD', new CreateDownloadRequestDto('file', str_repeat('a', 32), ['../x'])],
        ] as [$code, $request]) {
            try {
                $mapper->create($request);
                self::fail('Expected ' . $code);
            } catch (HttpException $error) {
                self::assertSame([$code, 422], [$error->getMessage(), $error->getCode()]);
            }
        }
    }
}
