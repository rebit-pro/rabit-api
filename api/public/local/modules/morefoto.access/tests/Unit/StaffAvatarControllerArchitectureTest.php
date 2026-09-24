<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

/**
 * @internal
 */
final class StaffAvatarControllerArchitectureTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffAvatarController.php',
            [
                'Morefoto\Access\Presentation\Controller',
                'Morefoto\Access\Presentation\Avatar\AvatarInputMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
                'Rebit\Share\Infrastructure\Controller\Responses\ImageResponse',
            ],
            [
                ['Morefoto\Access\Application\Avatar\UseCase\\', 'UseCase'],
                ['Morefoto\Access\Presentation\Avatar\Dto\\', 'RequestDto'],
            ],
        ));
    }
}
