<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class CatalogRemovalControllerArchitectureTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/CatalogRemovalController.php',
            [
                'Morefoto\Commerce\Presentation\Controller',
                'Morefoto\Commerce\Presentation\Catalog\ProductRemovalInputMapper',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Morefoto\Commerce\Application\Catalog\UseCase\\', 'UseCase'],
                ['Morefoto\Commerce\Presentation\Catalog\Dto\\', 'RequestDto'],
            ],
        ));
    }
}
