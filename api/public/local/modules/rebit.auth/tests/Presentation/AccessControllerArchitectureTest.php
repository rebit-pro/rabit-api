<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Presentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Tests\Support\CleanControllerSource;

/**
 * @internal
 */
final class AccessControllerArchitectureTest extends TestCase
{
    #[DataProvider('controllers')]
    public function testControllerKeepsTheCleanBoundary(string $file): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/' . $file,
            [
                'Rebit\Auth\Presentation\Controller',
                'Rebit\Auth\Presentation\Access\AccessInputMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Rebit\Auth\Application\Access\UseCase\\', 'UseCase'],
                ['Rebit\Auth\Presentation\Access\Dto\\', 'RequestDto'],
            ],
        ));
    }

    /** @return iterable<string, array{string}> */
    public static function controllers(): iterable
    {
        yield 'links' => ['AccessLinkController.php'];
        yield 'password' => ['PasswordController.php'];
    }

    public function testAccessControllersLogToTheAuthChannel(): void
    {
        self::assertSame(LogChannelEnum::auth, LogChannelEnum::resolveFromClassName('Rebit\Auth\Presentation\Controller\AccessLinkController'));
    }
}
