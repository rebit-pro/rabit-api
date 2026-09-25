<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Morefoto\Access\Infrastructure\Avatar\LocalAvatarStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LocalAvatarStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/avatar-storage-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new LocalAvatarStorage($this->root))->prune(7, null);
        @rmdir($this->root);
    }

    public function testKeepsOnlyTheCurrentVersionPrivate(): void
    {
        $storage = new LocalAvatarStorage($this->root);
        $storage->write(7, 1, new RenderedAvatarDto('full-1', 'thumb-1'));
        $storage->write(7, 2, new RenderedAvatarDto('full-2', 'thumb-2'));

        $storage->prune(7, 2);

        self::assertNull($storage->read(7, 1, AvatarVariantEnum::FULL));
        self::assertSame('full-2', $storage->read(7, 2, AvatarVariantEnum::FULL));
        self::assertSame('thumb-2', $storage->read(7, 2, AvatarVariantEnum::THUMB));
        self::assertSame(['2-256.webp', '2-64.webp'], array_values(array_diff((array)scandir($this->root . '/7'), ['.', '..'])));
        self::assertSame('0600', substr(sprintf('%o', fileperms($this->root . '/7/2-256.webp')), -4));
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->root . '/7')), -4));
    }

    public function testRemovingTheAvatarLeavesNoFiles(): void
    {
        $storage = new LocalAvatarStorage($this->root);
        $storage->write(7, 3, new RenderedAvatarDto('full', 'thumb'));

        $storage->prune(7, null);

        self::assertDirectoryDoesNotExist($this->root . '/7');
        self::assertNull($storage->read(7, 3, AvatarVariantEnum::THUMB));
    }
}
