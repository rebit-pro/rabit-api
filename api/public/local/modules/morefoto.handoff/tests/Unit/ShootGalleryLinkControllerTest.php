<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ShootGalleryLinkControllerTest extends TestCase
{
    public function testControllerUsesOnlyPresentationDtosAndItsUseCase(): void
    {
        self::assertSame([], CleanControllerSource::violations(dirname(__DIR__, 2) . '/lib/Presentation/Controller/ShootGalleryLinkController.php', [
            'Morefoto\Handoff\Presentation\Controller',
            'Morefoto\Handoff\Application\Link\UseCase\ShootGalleryLinkUseCase',
            'Morefoto\Handoff\Presentation\Link\Request\Dto\ShootGalleryLinkRequestDto',
            'Morefoto\Handoff\Presentation\Link\Result\Dto\ShootGalleryLinkResultDto',
            'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
        ], []));
    }
}
