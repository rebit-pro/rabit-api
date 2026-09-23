<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Tests\Support\CleanControllerSource;

/**
 * @internal
 */
final class StaffInvitationControllerArchitectureTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffInvitationController.php',
            [
                'Morefoto\Access\Presentation\Controller',
                'Morefoto\Access\Presentation\Staff\StaffInvitationInputMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            ],
            [
                ['Morefoto\Access\Application\Staff\UseCase\\', 'UseCase'],
                ['Morefoto\Access\Presentation\Staff\Dto\\', 'RequestDto'],
            ],
        ));
        self::assertSame(LogChannelEnum::access, LogChannelEnum::resolveFromClassName('Morefoto\Access\Presentation\Controller\StaffInvitationController'));
    }
}
