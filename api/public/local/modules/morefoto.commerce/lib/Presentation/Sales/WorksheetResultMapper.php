<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales;

use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetLineOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOrderOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetGroupOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetColumnOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOutputDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetFileResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetLineResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetOrderResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetGroupResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetColumnResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetVersionResultDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;

/** Преобразует внутреннюю проекцию ведомости в типизированный API-ответ без ORM и контрактных DTO поставщика. */
final readonly class WorksheetResultMapper
{
    private function version(PrintVersionOutputDto $value): WorksheetVersionResultDto
    {
        return new WorksheetVersionResultDto(
            type: $value->type,
            id: $value->id,
            institutionId: $value->institutionId,
            groupIds: $value->groupIds,
            number: $value->number,
            kind: $value->kind,
            status: $value->status,
            layoutVersion: $value->layoutVersion,
            createdAt: $value->createdAt,
            expiresAt: $value->expiresAt,
            files: $value->files,
            prints: $value->prints,
        );
    }

    public function map(WorksheetOutputDto $value): WorksheetResultDto
    {
        return new WorksheetResultDto(
            mode: $value->mode,
            archive: null === $value->archive ? null : $this->version($value->archive),
            finalAllowed: $value->finalAllowed,
            versions: array_map($this->version(...), $value->versions),
            groups: array_map($this->group(...), $value->groups),
            columns: array_map($this->column(...), $value->columns),
            total: $value->total,
            electronicTotal: $value->electronicTotal,
            prints: $value->prints,
            generatedAt: $value->generatedAt,
        );
    }

    private function column(WorksheetColumnOutputDto $value): WorksheetColumnResultDto
    {
        return new WorksheetColumnResultDto(
            key: $value->key,
            name: $value->name,
            format: $value->format,
        );
    }

    private function group(WorksheetGroupOutputDto $value): WorksheetGroupResultDto
    {
        return new WorksheetGroupResultDto(
            id: $value->id,
            name: $value->name,
            institutionName: $value->institutionName,
            shootName: $value->shootName,
            orders: array_map($this->order(...), $value->orders),
            deferredOrders: array_map($this->order(...), $value->deferredOrders),
            outsideOrders: array_map($this->order(...), $value->outsideOrders),
            total: $value->total,
            prints: $value->prints,
        );
    }

    private function order(WorksheetOrderOutputDto $value): WorksheetOrderResultDto
    {
        return new WorksheetOrderResultDto(
            id: $value->id,
            number: $value->number,
            firstName: $value->firstName,
            lastName: $value->lastName,
            childCodes: $value->childCodes,
            ambiguous: $value->ambiguous,
            paymentStatus: $value->paymentStatus,
            latePayment: $value->latePayment,
            total: $value->total,
            includedTotal: $value->includedTotal,
            discount: $value->discount,
            electronicTotal: $value->electronicTotal,
            giftCodes: $value->giftCodes,
            lines: array_map($this->line(...), $value->lines),
        );
    }

    private function line(WorksheetLineOutputDto $value): WorksheetLineResultDto
    {
        return new WorksheetLineResultDto(
            id: $value->id,
            kind: $value->kind,
            columnKey: $value->columnKey,
            productName: $value->productName,
            format: $value->format,
            quantity: $value->quantity,
            printCount: $value->printCount,
            prints: $value->prints,
            unitPrice: $value->unitPrice,
            total: $value->total,
            discount: $value->discount,
            coveredByGift: $value->coveredByGift,
            selected: $value->selected,
            files: array_map($this->file(...), $value->files),
        );
    }

    private function file(WorksheetFileOutputDto $value): WorksheetFileResultDto
    {
        return new WorksheetFileResultDto(
            filename: $value->filename,
            path: $value->path,
            printsInFile: $value->printsInFile,
            code: $value->code,
            childCode: $value->childCode,
        );
    }
}
