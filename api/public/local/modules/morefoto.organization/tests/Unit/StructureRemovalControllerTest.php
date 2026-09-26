<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Presentation\Structure\Dto\DeleteGroupRequestDto;
use Morefoto\Organization\Presentation\Structure\StructureRemovalInputMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class StructureRemovalControllerTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StructureRemovalController.php',
            [
                'Morefoto\Organization\Presentation\Controller',
                'Morefoto\Organization\Presentation\Structure\StructureRemovalInputMapper',
                'Morefoto\Organization\Domain\Structure\Enum\StructureKindEnum',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Morefoto\Organization\Application\Structure\UseCase\\', 'UseCase'],
                ['Morefoto\Organization\Presentation\Structure\Dto\\', 'RequestDto'],
            ],
        ));
    }

    public function testRouteIdAndBearerAreMapped(): void
    {
        $mapper = new StructureRemovalInputMapper();
        $request = new DeleteGroupRequestDto('12345678-ABCD-4abc-8abc-123456789abc', 'Bearer token-value');

        self::assertSame('12345678-abcd-4abc-8abc-123456789abc', $mapper->id($request)->value);
        self::assertSame('token-value', $mapper->bearer($request));
    }
}
