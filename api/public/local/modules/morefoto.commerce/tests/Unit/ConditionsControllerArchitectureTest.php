<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class ConditionsControllerArchitectureTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/ConditionsController.php',
            [
                'Morefoto\Commerce\Presentation\Controller',
                'Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper',
                'Morefoto\Commerce\Presentation\Conditions\ConditionsResultMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            ],
            [
                ['Morefoto\Commerce\Application\Conditions\UseCase\\', 'UseCase'],
                ['Morefoto\Commerce\Presentation\Conditions\Dto\\', 'RequestDto'],
            ],
        ));
    }
}
