<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Gallery\Mapper\GalleryAssignmentMapper;
use Morefoto\Media\Presentation\Gallery\GalleryResultMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class GalleryFilenameTest extends TestCase
{
    #[DataProvider('filenames')]
    public function testGalleryKeepsTheUploadedFilenameAndAssignment(string $filename): void
    {
        $photo = new GalleryAssignmentMapper()->fromRow([
            'ASSIGNMENT_ID' => 'assignment-1', 'PHOTO_ID' => 'photo-1', 'CHILD_ID' => 'child-1', 'NATIVE_CHILD_ID' => '7',
            'CODE' => 'AB', 'SEQUENCE_NO' => '3', 'UF_FILENAME' => $filename, 'UF_WIDTH' => '1200', 'UF_HEIGHT' => '800', 'UF_REVISION' => '2',
        ]);
        $group = new GalleryGroupOutputDto(1, 2, 'group-1', 'Учреждение', 'Съёмка', 'Группа', 'regular', null, null, null, 1);
        $output = new GalleryResultMapper()->map(new GalleryAccessOutputDto($group, 'open', '2026-11-07T10:00:00Z', 1, [$photo]), str_repeat('a', 64));

        self::assertSame($filename, $photo->filename);
        self::assertSame($filename, $output->children[0]['photos'][0]['filename']);
        self::assertSame(['photo-1', 'assignment-1', 'AB003'], [
            $output->children[0]['photos'][0]['id'], $output->children[0]['photos'][0]['assignmentId'], $output->children[0]['photos'][0]['code'],
        ]);
        self::assertSame([
            'id', 'assignmentId', 'code', 'filename', 'thumbSrc', 'previewSrc', 'width', 'height',
        ], array_keys($output->children[0]['photos'][0]));
    }

    public static function filenames(): iterable
    {
        yield 'uppercase extension' => ['IMG_00217.JPEG'];
        yield 'leading zeroes' => ['0007.webp'];
        yield 'unicode and spaces' => ['Осень 0003.png'];
        yield 'HTML stays data' => ['<кадр>&0004.JPG'];
    }

    public function testExistingConstructorWithoutFilenameRemainsCompatible(): void
    {
        $photo = new GalleryAssignmentOutputDto('assignment-1', 'photo-1', 'child-1', 7, 'A', 'A001', 1200, 800, 1);

        self::assertSame('', $photo->filename);
    }
}
