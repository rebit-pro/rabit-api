<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class SupportArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testControllersUseTypedRequestsAndSharedInfrastructureOnly(): void
    {
        $files = glob(self::MODULE . '/Presentation/Controller/*.php') ?: [];
        self::assertCount(3, $files);
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            foreach (['use Bitrix\\', 'HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'Filter(', 'getenv', 'json_decode', 'getRequest()',
                'configureActions', 'getExceptionResponse', 'Cache-Control', 'HttpException', 'try {', '$this->json(['] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, basename($file));
            }
            preg_match_all('/public function \w+Action\(([^)]*)\)/', $source, $actions);
            self::assertNotEmpty($actions[1]);
            foreach ($actions[1] as $argument) {
                self::assertMatchesRegularExpression('/^\w+RequestDto \$request$/', $argument, basename($file));
            }
            self::assertSame(LogChannelEnum::support, LogChannelEnum::resolveFromClassName('Morefoto\Support\Presentation\Controller\\' . basename($file, '.php')));
        }
    }

    public function testDtosHaveNoBehaviour(): void
    {
        $files = array_merge(
            glob(self::MODULE . '/Application/*/Dto/*.php') ?: [],
            glob(self::MODULE . '/Presentation/*/Request/Dto/*.php') ?: [],
            glob(self::MODULE . '/Presentation/*/Result/Dto/*.php') ?: [],
        );
        self::assertGreaterThanOrEqual(20, count($files));
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            preg_match_all('/function\s+(\w+)\(/', $source, $methods);
            self::assertSame(['__construct'], $methods[1], $file);
        }
    }

    public function testUseCasesAndServicesExplainPurposeInRussian(): void
    {
        $files = array_merge(
            glob(self::MODULE . '/Application/*/UseCase/*.php') ?: [],
            glob(self::MODULE . '/Application/*/Service/*.php') ?: [],
            glob(self::MODULE . '/Domain/*/Service/*.php') ?: [],
        );
        self::assertCount(16, $files);
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/)[\s\S])*[А-Яа-яЁё](?:(?!\*\/)[\s\S])*\*\/\s*(?:final\s+)?(?:readonly\s+)?class\s/u', $source, $file);
        }
    }

    public function testApplicationAndDomainDoNotDependOnBitrixOrOuterLayers(): void
    {
        foreach (['Application', 'Domain'] as $layer) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MODULE . '/' . $layer));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $source = (string)file_get_contents($file->getPathname());
                foreach (['Bitrix\\', 'Morefoto\Support\Infrastructure', 'Morefoto\Support\Presentation', 'ServiceLocator', 'getenv'] as $forbidden) {
                    self::assertStringNotContainsString($forbidden, $source, $file->getPathname());
                }
            }
        }
    }

    public function testResultsNeverCarryInternalSecretsOrState(): void
    {
        foreach (glob(self::MODULE . '/Presentation/*/Result/Dto/*.php') ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression('/\$(keyHash|galleryToken|token|mid|maxMid|idempotencyKey|attempts|lastErrorCode|context)\b/i', (string)file_get_contents($file), $file);
        }
    }
}
