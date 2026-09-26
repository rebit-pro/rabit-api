<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class LegalArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testControllersUseTypedRequestsAndSharedInfrastructureOnly(): void
    {
        foreach (['LegalDocumentController', 'StaffConsentController'] as $controller) {
            $source = (string)file_get_contents(self::MODULE . '/Presentation/Controller/' . $controller . '.php');
            foreach (['use Bitrix\\', 'HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'json_decode', 'getRequest()', 'configureActions', 'getExceptionResponse', 'Cache-Control', 'HttpException'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $controller);
            }
            preg_match_all('/public function \w+Action\(([^)]*)\)/', $source, $actions);
            self::assertNotEmpty($actions[1]);
            foreach ($actions[1] as $argument) {
                self::assertMatchesRegularExpression('/^\w+RequestDto \$request$/', $argument);
            }
        }
    }

    public function testDtosHaveNoBehaviour(): void
    {
        $files = array_merge(
            glob(self::MODULE . '/Application/*/Dto/*.php') ?: [],
            glob(self::MODULE . '/Presentation/*/Dto/*.php') ?: [],
            [self::MODULE . '/Domain/Document/Entity/LegalDocumentVersion.php'],
        );
        self::assertNotEmpty($files);
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
        self::assertCount(8, $files);
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/)[\s\S])*[А-Яа-яЁё](?:(?!\*\/)[\s\S])*\*\/\s*(?:final\s+)?(?:readonly\s+)?class\s/u', $source, $file);
        }
    }

    public function testConsentJournalKeepsNoNetworkIdentifiers(): void
    {
        $migration = (string)file_get_contents(__DIR__ . '/../../../../php_interface/migrations.foundation/Version20260925230001.php');

        self::assertStringContainsString('mf_legal_consent', $migration);
        self::assertDoesNotMatchRegularExpression('/\b(IP|USER_AGENT|REMOTE_ADDR)\b/', $migration);
    }
}
