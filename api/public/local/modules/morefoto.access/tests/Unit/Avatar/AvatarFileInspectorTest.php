<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Infrastructure\Avatar\AvatarFileInspector;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class AvatarFileInspectorTest extends TestCase
{
    private AvatarImages $images;

    protected function setUp(): void
    {
        $this->images = new AvatarImages();
    }

    protected function tearDown(): void
    {
        $this->images->cleanup();
    }

    public function testAcceptsAPhotoByItsContent(): void
    {
        $path = $this->images->png(1200, 800);

        $inspected = (new AvatarFileInspector())->inspect(new UploadedAvatarInputDto($path, 1));

        self::assertSame('image/png', $inspected->mimeType);
        self::assertSame([1200, 800], [$inspected->width, $inspected->height]);
        self::assertSame(hash_file('sha256', $path), $inspected->fingerprint);
        self::assertSame(filesize($path), $inspected->bytes);
    }

    public function testRejectsFilesThatAreNotAvatars(): void
    {
        $this->assertCode('UNSUPPORTED_AVATAR_FORMAT', $this->images->file('<svg xmlns="http://www.w3.org/2000/svg"/>'));
        $this->assertCode('AVATAR_TOO_SMALL', $this->images->png(50, 80));
        $this->assertCode('AVATAR_TOO_LARGE', $this->images->file(str_repeat("\0", 5242881)));
        $this->assertCode('CORRUPTED_AVATAR', $this->images->file(''));
        // A PNG header that promises 6000 × 6000 pixels is refused before anything decodes it.
        $header = "\x89PNG\r\n\x1A\n" . pack('N', 13) . 'IHDR' . pack('NN', 6000, 6000) . "\x08\x02\x00\x00\x00";
        $this->assertCode('AVATAR_TOO_LARGE', $this->images->file($header . pack('N', crc32(substr($header, 12)))));
    }

    private function assertCode(string $code, string $path): void
    {
        try {
            (new AvatarFileInspector())->inspect(new UploadedAvatarInputDto($path, 1));
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
            self::assertSame(422, $error->getCode());
        }
    }
}
