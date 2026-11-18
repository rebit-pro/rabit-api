<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Gallery\Dto\ShootGalleryOutputDto;
use Morefoto\Media\Presentation\Gallery\ShootGalleryResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ShootGalleryControllerTest extends TestCase
{
    public function testControllerUsesOnlyPresentationDtosAndItsUseCase(): void
    {
        self::assertSame([], CleanControllerSource::violations(dirname(__DIR__, 2) . '/lib/Presentation/Controller/ShootGalleryController.php', [
            'Morefoto\Media\Presentation\Controller',
            'Morefoto\Media\Application\Gallery\UseCase\GetShootGalleryUseCase',
            'Morefoto\Media\Presentation\Gallery\Dto\ShootGalleryRequestDto',
            'Morefoto\Media\Presentation\Gallery\Dto\ShootCoverRequestDto',
            'Morefoto\Media\Presentation\Gallery\ShootGalleryResultMapper',
            'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController',
            'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
            'Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse',
        ], []));
    }

    public function testTileNavigationUsesOnlyTheScopedCoverAndGroupLink(): void
    {
        $mapper = new ShootGalleryResultMapper();
        $view = new ShootGalleryOutputDto('Весна', 'Школа', [
            ['id' => 'group-a', 'name' => '1А', 'key' => 'shortalias', 'hasCover' => true],
            ['id' => 'group-b', 'name' => '1Б', 'key' => 'otheralias', 'hasCover' => false],
        ]);
        $result = $mapper->map($view, 'shootkey');
        self::assertSame('open', $result->state);
        self::assertSame([
            ['name' => '1А', 'groupPath' => '/g/shortalias', 'coverUrl' => '/api/v1/public/galleries/shoots/shootkey/groups/group-a/cover'],
            ['name' => '1Б', 'groupPath' => '/g/otheralias', 'coverUrl' => null],
        ], $result->groups);
        self::assertSame('preparing', $mapper->map(new ShootGalleryOutputDto('Весна', 'Школа', []), 'shootkey')->state);
    }
}
