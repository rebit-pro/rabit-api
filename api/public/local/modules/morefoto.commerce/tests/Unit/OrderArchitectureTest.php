<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testOrderControllersUseTypedRequestsAndSharedInfrastructureOnly(): void
    {
        foreach (['OrderController', 'StaffOrderController'] as $controller) {
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

    public function testOrderDtosHaveNoBehaviour(): void
    {
        foreach (['/Application/Order/Dto', '/Presentation/Order/Dto', '/Domain/Order/ValueObject/OrderBuyer.php', '/Domain/Order/ValueObject/OrderSearchCriteria.php', '/Application/Storefront/Dto'] as $path) {
            $files = str_ends_with($path, '.php') ? [self::MODULE . $path] : (glob(self::MODULE . $path . '/*.php') ?: []);
            self::assertNotEmpty($files, $path);
            foreach ($files as $file) {
                $source = (string)file_get_contents($file);
                self::assertMatchesRegularExpression('/function __construct\([\s\S]*?\)\s*\{\s*\}\s*\}\s*$/', $source, $file);
                preg_match_all('/function\s+(\w+)\(/', $source, $methods);
                self::assertSame(['__construct'], $methods[1], $file);
            }
        }
    }

    public function testUseCasesAndServicesExplainPurposeInRussian(): void
    {
        $files = array_merge(
            glob(self::MODULE . '/Application/Order/UseCase/*.php') ?: [],
            glob(self::MODULE . '/Application/Order/Service/*.php') ?: [],
            glob(self::MODULE . '/Domain/Order/Service/*.php') ?: [],
            [self::MODULE . '/Application/Storefront/UseCase/ValidateQuoteUseCase.php', self::MODULE . '/Application/Storefront/UseCase/GetStorefrontCatalogUseCase.php'],
        );
        self::assertCount(15, $files);
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            // BypassFinals may strip modifiers on read; the class-level docblock itself must stay right before the class.
            self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/)[\s\S])*[А-Яа-яЁё](?:(?!\*\/)[\s\S])*\*\/\s*(?:final\s+)?(?:readonly\s+)?class\s/u', $source, $file);
        }
    }

    public function testStaffProjectionsCannotCarryBuyerSecrets(): void
    {
        foreach (['StaffOrderResultDto', 'StaffOrderDetailResultDto', 'StaffOrderListResultDto', 'BuyerOrderResultDto'] as $dto) {
            $source = (string)file_get_contents(self::MODULE . '/Presentation/Order/Dto/' . $dto . '.php');
            self::assertDoesNotMatchRegularExpression('/\$(accessKey|galleryToken|requestId|idempotencyKey|operations)\b/i', $source, $dto);
        }
    }
}
