<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Gallery\Service\GalleryAccess;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GalleryContextTest extends TestCase
{
    public function testCatalogAndPreviewAccessDoNotFetchWholeGallery(): void
    {
        $keys = $this->createMock(GalleryCapabilityRepository::class);
        $keys->expects(self::once())->method('find')->with(hash('sha256', str_repeat('a', 64)))
            ->willReturn(['GROUP_PUBLIC_ID' => 'group-id', 'REVISION' => 2])
        ;
        $groups = $this->createMock(GalleryGroupInterface::class);
        $groups->expects(self::once())->method('get')->with('group-id')->willReturn(
            new GalleryGroupOutputDto(1, 2, 'group-id', 'Institution', 'Shoot', 'Group', 'regular', '2026-09-21 00:00:00', '2026-09-28 00:00:00', 1),
        );
        $photos = $this->createMock(GalleryPhotoRepository::class);
        $photos->expects(self::never())->method('list');
        $photos->expects(self::never())->method('photoId');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-21T10:00:00Z'));
        $context = (new GalleryAccess($keys, $groups, $photos, new GalleryAvailability(), $clock))->context(str_repeat('a', 64));
        self::assertSame('open', $context->state);
        self::assertSame(2, $context->capabilityRevision);
        self::assertSame('group-id', $context->group->publicId);
    }
}
