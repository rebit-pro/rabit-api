<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E10: the COM-15/16/17 boundary — a clean controller, Russian purpose docs and an answer without buyer data.
 *
 * @internal
 */
final class SalesReportArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testTheControllerUsesTypedRequestsUseCasesAndTheMapperOnly(): void
    {
        $source = (string)file_get_contents(self::MODULE . '/Presentation/Controller/SalesReportController.php');
        foreach (['use Bitrix\\', 'HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'json_decode', 'getRequest()', 'configureActions', 'getExceptionResponse', 'Cache-Control', 'HttpException', 'Repository', 'Mpdf'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        preg_match_all('/public function \w+Action\(([^)]*)\)/', $source, $actions);
        self::assertSame(['SalesReportRequestDto $request', 'SalesReportRequestDto $request', 'ChildPreviewRequestDto $request'], $actions[1]);
    }

    public function testUseCasesAndServicesExplainTheirPurposeInRussian(): void
    {
        foreach ([
            '/Application/Sales/UseCase/GetSalesReportUseCase.php',
            '/Application/Sales/UseCase/ExportSalesReportPdfUseCase.php',
            '/Application/Sales/UseCase/GetChildPreviewUseCase.php',
            '/Application/Sales/Service/SalesReportScope.php',
            '/Domain/Sales/Service/ChildSalesStatusPolicy.php',
        ] as $file) {
            $source = (string)file_get_contents(self::MODULE . $file);
            self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/)[\s\S])*[А-Яа-яЁё](?:(?!\*\/)[\s\S])*\*\/\s*(?:final\s+)?(?:readonly\s+)?class\s/u', $source, $file);
        }
    }

    public function testTheUseCasesDoNotRenderOrQueryDirectly(): void
    {
        foreach (glob(self::MODULE . '/Application/Sales/UseCase/*Report*.php') ?: [] as $file) {
            $source = (string)file_get_contents($file);
            foreach (['Mpdf', '<table', 'Application::getConnection', 'SELECT '] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function testTheAnswerAndThePdfCarryOnlyOrderIdentifiersAndChildNames(): void
    {
        $files = array_merge(
            glob(self::MODULE . '/Presentation/Sales/Dto/*Result*.php') ?: [],
            [self::MODULE . '/Infrastructure/Sales/Pdf/SalesReportHtml.php', self::MODULE . '/Domain/Sales/Repository/SalesReportRepository.php'],
        );
        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/\$(email|buyer\w*|phone|accessKey|orderId|orderKey|photoId)\b|BUYER_|PHOTO_PUBLIC_ID AS/i', $source, $file);
        }
    }
}
