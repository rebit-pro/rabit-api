<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/morefoto.media/tests/bootstrap.php';

/**
 * @internal
 */
final class StorefrontArchitectureTest extends TestCase
{
    public function testNewControllersUseTypedRequestsAndSharedInfrastructureOnly(): void
    {
        $modules = dirname(__DIR__, 3);
        foreach ([
            $modules . '/morefoto.commerce/lib/Presentation/Controller/StorefrontController.php',
            $modules . '/morefoto.media/lib/Presentation/Controller/GalleryController.php',
            $modules . '/morefoto.media/lib/Presentation/Controller/ManagedPreviewController.php',
        ] as $filename) {
            $source = (string)file_get_contents($filename);
            foreach (['use Bitrix\\', 'HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'json_decode', 'configureActions', 'getExceptionResponse'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
            preg_match_all('/public function \w+Action\(([^)]*)\)/', $source, $actions);
            self::assertNotEmpty($actions[1]);
            foreach ($actions[1] as $argument) {
                self::assertMatchesRegularExpression('/^\w+RequestDto \$request$/', $argument);
            }
        }
    }

    public function testStorefrontDtosHaveNoBehaviour(): void
    {
        $modules = dirname(__DIR__, 3);
        $directories = [
            $modules . '/morefoto.commerce/lib/Application/Storefront/Dto',
            $modules . '/morefoto.commerce/lib/Presentation/Storefront/Dto',
            $modules . '/morefoto.media/lib/Presentation/Gallery/Dto',
            $modules . '/morefoto.media/lib/Application/Gallery/Dto',
            $modules . '/rebit.share/lib/Contracts/Media/Dto/Gallery*.php',
            $modules . '/rebit.share/lib/Contracts/Organization/Dto/GalleryGroupOutputDto.php',
            $modules . '/rebit.share/lib/Application/Contract/File/Dto',
        ];
        foreach ($directories as $directory) {
            foreach (glob(str_ends_with($directory, '.php') ? $directory : $directory . '/*.php') ?: [] as $file) {
                $source = (string)file_get_contents($file);
                // BypassFinals may remove modifiers on file_get_contents; methods and empty bodies remain observable.
                self::assertMatchesRegularExpression('/function __construct\([\s\S]*?\)\s*\{\s*\}\s*\}\s*$/', $source, $file);
                preg_match_all('/function\s+(\w+)\(/', $source, $methods);
                self::assertSame(['__construct'], $methods[1], $file);
            }
        }
    }
}
