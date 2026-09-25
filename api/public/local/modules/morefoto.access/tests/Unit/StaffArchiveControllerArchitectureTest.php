<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

/**
 * @internal
 */
final class StaffArchiveControllerArchitectureTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffArchiveController.php',
            [
                'Morefoto\Access\Presentation\Controller',
                'Morefoto\Access\Presentation\Staff\StaffArchiveInputMapper',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Morefoto\Access\Application\Staff\UseCase\\', 'UseCase'],
                ['Morefoto\Access\Presentation\Staff\Dto\\', 'RequestDto'],
            ],
        ));
    }
}
