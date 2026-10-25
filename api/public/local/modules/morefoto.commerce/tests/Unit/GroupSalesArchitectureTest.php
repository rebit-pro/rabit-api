<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E9: the COM-14 boundary — a clean controller, plain DTOs and an answer without buyer data.
 *
 * @internal
 */
final class GroupSalesArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testTheControllerUsesTheTypedRequestTheUseCaseAndTheMapperOnly(): void
    {
        $source = (string)file_get_contents(self::MODULE . '/Presentation/Controller/GroupSalesController.php');
        foreach (['use Bitrix\\', 'HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'json_decode', 'getRequest()', 'configureActions', 'getExceptionResponse', 'Cache-Control', 'HttpException', 'Repository'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        preg_match_all('/public function \w+Action\(([^)]*)\)/', $source, $actions);
        self::assertSame(['GroupSalesRequestDto $request'], $actions[1]);
    }

    public function testSalesDtosHaveNoBehaviour(): void
    {
        $files = array_merge(glob(self::MODULE . '/Application/Sales/Dto/*.php') ?: [], glob(self::MODULE . '/Presentation/Sales/Dto/*.php') ?: []);
        // E9 and the E10 report share the Sales DTO directories.
        self::assertCount(20, $files);
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            self::assertMatchesRegularExpression('/function __construct\([\s\S]*?\)\s*\{\s*\}\s*\}\s*$/', $source, $file);
            preg_match_all('/function\s+(\w+)\(/', $source, $methods);
            self::assertSame(['__construct'], $methods[1], $file);
        }
    }

    public function testTheUseCaseExplainsItsPurposeInRussian(): void
    {
        $source = (string)file_get_contents(self::MODULE . '/Application/Sales/UseCase/GetGroupSalesUseCase.php');
        self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/)[\s\S])*[А-Яа-яЁё](?:(?!\*\/)[\s\S])*\*\/\s*(?:final\s+)?(?:readonly\s+)?class\s/u', $source);
    }

    public function testTheAnswerCarriesNoBuyerData(): void
    {
        foreach (['GroupSalesResultDto', 'ProductSalesResultDto'] as $dto) {
            $source = (string)file_get_contents(self::MODULE . '/Presentation/Sales/Dto/' . $dto . '.php');
            self::assertDoesNotMatchRegularExpression('/\$(email|buyer\w*|name\w*Buyer|phone|number|accessKey|orderId|orderKey|childCode)\b/i', $source, $dto);
        }
    }
}
