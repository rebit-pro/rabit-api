<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Infrastructure\Lead;

use PHPUnit\Framework\TestCase;
use Rebit\Notification\Infrastructure\Lead\UploadedFileValidator;
use Rebit\Share\Shared\Exception\ValidationHttpException;

/**
 * @internal
 */
final class UploadedFileValidatorTest extends TestCase
{
    private const int MAX_BYTES = 15 * 1024 * 1024;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function testReturnsNullWhenNoFile(): void
    {
        $validator = $this->createValidator();

        self::assertNull($validator->validate(null));
        self::assertNull($validator->validate([]));
    }

    public function testReturnsNullWhenUploadErrNoFile(): void
    {
        $validator = $this->createValidator();

        self::assertNull($validator->validate(['error' => UPLOAD_ERR_NO_FILE]));
    }

    public function testThrowsOnIniSizeUploadError(): void
    {
        $validator = $this->createValidator();

        $this->expectException(ValidationHttpException::class);
        $validator->validate(['error' => UPLOAD_ERR_INI_SIZE, 'name' => 'big.pdf']);
    }

    public function testThrowsWhenSizeExceedsLimit(): void
    {
        $path = $this->makeTempFile("%PDF-1.4\n%âãÏÓ\n");
        $validator = $this->createValidator();

        $this->expectException(ValidationHttpException::class);
        $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'tz.pdf',
            'tmp_name' => $path,
            'size' => self::MAX_BYTES + 1,
        ]);
    }

    public function testThrowsOnEmptyFile(): void
    {
        $path = $this->makeTempFile('');
        $validator = $this->createValidator();

        $this->expectException(ValidationHttpException::class);
        $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'tz.pdf',
            'tmp_name' => $path,
            'size' => 0,
        ]);
    }

    public function testThrowsOnDisallowedMimeType(): void
    {
        // GIF не входит в whitelist; finfo определит image/gif по содержимому.
        $path = $this->makeTempFile("GIF89a\x01\x00\x01\x00\x00\x00\x00;");
        $validator = $this->createValidator();

        $this->expectException(ValidationHttpException::class);
        $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'image.png',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);
    }

    public function testRejectsMimeMismatchByContentNotExtension(): void
    {
        // Файл с расширением .pdf, но HTML внутри → text/html, не из whitelist.
        $path = $this->makeTempFile('<!DOCTYPE html><html><body>hi</body></html>');
        $validator = $this->createValidator();

        $this->expectException(ValidationHttpException::class);
        $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'fake.pdf',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);
    }

    public function testAcceptsValidPdf(): void
    {
        $path = $this->makeTempFile("%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF");
        $validator = $this->createValidator();

        $attachment = $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'Тех задание.pdf',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);

        self::assertNotNull($attachment);
        self::assertSame('application/pdf', $attachment->mimeType);
        self::assertSame($path, $attachment->path);
        self::assertSame('Тех задание.pdf', $attachment->name);
        self::assertGreaterThan(0, $attachment->size);
    }

    public function testAcceptsPlainText(): void
    {
        $path = $this->makeTempFile("Просто текстовое ТЗ для проекта.\n");
        $validator = $this->createValidator();

        $attachment = $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'tz.txt',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);

        self::assertNotNull($attachment);
        self::assertSame('text/plain', $attachment->mimeType);
    }

    public function testSanitizesPathTraversalInName(): void
    {
        $path = $this->makeTempFile("%PDF-1.4\nendobj\n%%EOF");
        $validator = $this->createValidator();

        $attachment = $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => "../../etc/pa\x00sswd.pdf",
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);

        self::assertNotNull($attachment);
        self::assertStringNotContainsString('/', $attachment->name);
        self::assertStringNotContainsString('..', $attachment->name);
        self::assertStringNotContainsString("\x00", $attachment->name);
    }

    public function testFallsBackWhenNameEmpty(): void
    {
        $path = $this->makeTempFile("%PDF-1.4\nendobj\n%%EOF");
        $validator = $this->createValidator();

        $attachment = $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => '',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);

        self::assertNotNull($attachment);
        self::assertSame('tz.pdf', $attachment->name);
    }

    public function testThrowsWhenNotUploadedFile(): void
    {
        // Дефолтный валидатор (без подмены isUploadedFile) — путь не является HTTP-загрузкой.
        $path = $this->makeTempFile("%PDF-1.4\nendobj\n%%EOF");
        $validator = new UploadedFileValidator(self::MAX_BYTES);

        $this->expectException(ValidationHttpException::class);
        $validator->validate([
            'error' => UPLOAD_ERR_OK,
            'name' => 'tz.pdf',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);
    }

    private function makeTempFile(string $content): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'lead_upload_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Валидатор с подменённой проверкой is_uploaded_file: в юнит-тестах
     * временные файлы не являются настоящими HTTP-загрузками.
     */
    private function createValidator(): UploadedFileValidator
    {
        return new class(self::MAX_BYTES) extends UploadedFileValidator {
            protected function isUploadedFile(string $path): bool
            {
                return is_file($path);
            }
        };
    }
}
