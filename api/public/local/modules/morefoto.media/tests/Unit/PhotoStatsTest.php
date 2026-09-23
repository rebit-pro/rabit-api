<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Application\Photo\UseCase\ListPhotosUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class PhotoStatsTest extends TestCase
{
    public function testStatsCoverTheResolvedGroupWithoutPageFilters(): void
    {
        $stats = ['byStatus' => ['processing' => 1, 'ready' => 8, 'failed' => 1, 'duplicate' => 2], 'unassigned' => 3];
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('photos')->willReturn($this->createStub(Result::class));
        $photos->method('count')->willReturn(0);
        // The page is filtered by child, assignment and status; the processing split is not, but stays inside the same group.
        $photos->expects(self::once())->method('stats')->with(5, 10)->willReturn($stats);
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(3, 5, '12345678-abcd-4abc-8abc-123456789abc', 10, '22345678-abcd-4abc-8abc-123456789abc', true));
        $scopes->method('groups')->willReturn([]);
        $media = $this->createStub(MediaMutationRepository::class);
        $media->method('covers')->willReturn([]);
        $media->method('revision')->willReturn(4);
        $access = $this->createMock(AccessGuardInterface::class);
        $access->expects(self::once())->method('assertCan')->with(21, 'media.manage', 3, 10);

        $page = (new ListPhotosUseCase($photos, $media, new PhotoRowMapper(), $scopes, $access))
            ->execute(21, '12345678-abcd-4abc-8abc-123456789abc', new ListPhotosInputDto('22345678-abcd-4abc-8abc-123456789abc', 1, 50, 'A-7', false, 'ready'))
        ;

        self::assertSame($stats, $page->stats);
    }
}
