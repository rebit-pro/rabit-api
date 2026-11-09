<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportNodeOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Application\Sales\UseCase\ExportSalesReportPdfUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetSalesReportUseCase;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportRequestDto;
use Morefoto\Commerce\Presentation\Sales\SalesReportResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E10: the COM-15/16/17 boundary — a clean controller, Russian purpose docs and an answer without buyer data.
 *
 * @internal
 */
final class SalesReportArchitectureTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../lib';

    public function testPdfViewTraversesTheRealQueryMapperAndPreservesLegacyDefault(): void
    {
        $mapper = new SalesReportResultMapper();
        foreach ([null => true, 'all' => true, 'ordered' => false] as $view => $expected) {
            $values = '' === $view ? [] : ['childrenView' => $view];
            $request = ArrayToDtoMapper::map(StrictRequestValues::normalize($values, SalesReportRequestDto::class, false), SalesReportRequestDto::class);
            self::assertSame($expected, $mapper->input($request)->includeUnordered);
        }
        $this->expectException(ValidationHttpException::class);
        ArrayToDtoMapper::map(StrictRequestValues::normalize(['childrenView' => 'paid-only'], SalesReportRequestDto::class, false), SalesReportRequestDto::class);
    }

    public function testPdfExportKeepsTheAuthorizedReportAndPassesTheSelectedViewWithoutMediaReads(): void
    {
        $report = new SalesReportOutputDto(SalesReportLevelEnum::GROUP, null, null, new SalesReportNodeOutputDto('g', 'Группа'), new SalesTotalsOutputDto(0, 0, 0, 0, 0, 0, 0, 0), [], [], [], new \DateTimeImmutable('2026-11-09T21:30:00+00:00'));
        $input = new SalesReportInputDto(groupId: 'g', includeUnordered: false);
        $reader = $this->createMock(GetSalesReportUseCase::class);
        $reader->expects(self::once())->method('execute')->with(7, $input)->willReturn($report);
        $writer = $this->createMock(SalesReportPdfWriterInterface::class);
        $writer->expects(self::once())->method('pdf')->with($report, false)->willReturn('%PDF-test');

        $file = (new ExportSalesReportPdfUseCase($reader, $writer))->execute(7, $input);

        self::assertSame('%PDF-test', $file->content);
        self::assertSame('morefoto-otchet-group-2026-11-10.pdf', $file->filename);
    }

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
