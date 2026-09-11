<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Domain\File;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Service\FileUploadService;
use Rebit\Share\Domain\File\Service\UploadedFileOwnershipService;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Infrastructure\Helpers\ValidationHelper;

/** @internal */
final class W03FileUploadTest extends TestCase
{
    private string $temporaryFile;

    protected function setUp(): void
    {
        \CFile::resetMockFiles();
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'w03-');
        file_put_contents($this->temporaryFile, "Fixture\n");
    }

    protected function tearDown(): void
    {
        unlink($this->temporaryFile);
        \CFile::resetMockFiles();
    }

    public function testAttributeValidationAcceptsRealModuleAndRejectsTraversal(): void
    {
        ValidationHelper::validate($this->dto());
        $this->expectException(ValidationHttpException::class);
        $this->expectExceptionMessage('Ошибка валидации данных.');
        ValidationHelper::validate($this->dto('../private'));
    }

    public function testAttributeValidationRejectsOversizedModule(): void
    {
        $this->expectException(ValidationHttpException::class);
        ValidationHelper::validate($this->dto(str_repeat('a', 51)));
    }

    public function testSuccessfulUploadKeepsExistingResultAndRecordsOwnership(): void
    {
        $owner = $this->createMock(UploadedFileOwnershipService::class);
        $owner->expects(self::once())->method('remember')->with(1, 501, 'rebit.share');
        $result = (new FileUploadService($owner))->upload($this->dto(), 501);
        self::assertSame(1, $result->id);
        self::assertSame('fixture.txt', $result->name);
        self::assertSame(8, $result->size);
        self::assertSame('text/plain', $result->type);
        self::assertSame('/upload/rebit.share/fixture.txt', $result->src);
    }

    public function testOwnershipFailureCleansSavedFileAndNeverReturnsSuccess(): void
    {
        $owner = $this->createMock(UploadedFileOwnershipService::class);
        $failure = new FileUploadFailedException('Fixture durable write failed.');
        $owner->expects(self::once())->method('remember')->willThrowException($failure);
        try {
            (new FileUploadService($owner))->upload($this->dto(), 501);
            self::fail('Upload must fail without a durable owner.');
        } catch (FileUploadFailedException $exception) {
            self::assertSame($failure, $exception);
            self::assertFalse(\CFile::GetFileArray(1));
        }
    }

    private function dto(string $moduleId = 'rebit.share'): UploadRequestFileRequestDto
    {
        return new UploadRequestFileRequestDto(
            moduleId: $moduleId,
            name: 'fixture.txt',
            type: 'text/plain',
            tmpName: $this->temporaryFile,
            size: 8,
        );
    }
}
