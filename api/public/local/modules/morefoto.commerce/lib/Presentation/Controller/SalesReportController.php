<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Sales\UseCase\ExportSalesReportPdfUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetChildPreviewUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetSalesReportUseCase;
use Morefoto\Commerce\Presentation\Sales\Dto\ChildPreviewRequestDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportRequestDto;
use Morefoto\Commerce\Presentation\Sales\SalesReportResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\PdfFileResponse;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

final class SalesReportController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetSalesReportUseCase $report,
        private readonly ExportSalesReportPdfUseCase $pdf,
        private readonly GetChildPreviewUseCase $preview,
        private readonly SalesReportResultMapper $result,
    ) {
        parent::__construct();
    }

    public function reportAction(SalesReportRequestDto $request): ControllerJson
    {
        return $this->json($this->result->report($this->report->execute($this->getAuthUserId(), $this->result->input($request))));
    }

    public function pdfAction(SalesReportRequestDto $request): PdfFileResponse
    {
        return $this->pdfFile($this->pdf->execute($this->getAuthUserId(), $this->result->input($request)));
    }

    public function childPreviewAction(ChildPreviewRequestDto $request): PreviewResponse
    {
        return $this->preview($this->preview->execute($this->getAuthUserId(), $request->childId, $request->variant));
    }
}
