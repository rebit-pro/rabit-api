<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution;

use Morefoto\Production\Application\Institution\Dto\InstitutionDownloadOutputDto;
use Morefoto\Production\Application\Institution\Dto\InstitutionProductionOutputDto;
use Morefoto\Production\Application\Institution\Dto\InstitutionVersionOutputDto;
use Morefoto\Production\Presentation\Institution\Result\Dto\InstitutionDownloadResultDto;
use Morefoto\Production\Presentation\Institution\Result\Dto\InstitutionProductionResultDto;
use Morefoto\Production\Presentation\Institution\Result\Dto\InstitutionVersionResultDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

/** Оформляет даты и публичные поля архива учреждения, оставляя внутренние источники и пути хранения за границей HTTP. */
final readonly class InstitutionResultMapper
{
    public function production(InstitutionProductionOutputDto $output): InstitutionProductionResultDto
    {
        return new InstitutionProductionResultDto(
            institutionId: $output->institutionId,
            institutionName: $output->institutionName,
            state: $output->state,
            canManage: $output->canManage,
            buildFrom: $this->moment($output->buildFrom),
            outsideLines: $output->outsideLines,
            waitingOrders: $output->waitingOrders,
            error: $output->error,
            blockers: array_map(static fn(GroupDirectoryItemOutputDto $group): array => [
                'id' => $group->id, 'name' => $group->name, 'shootName' => $group->shootName, 'deadlineAt' => $group->calendar->closesAt,
            ], $output->blockers),
            versions: array_map($this->version(...), $output->versions),
        );
    }

    public function version(InstitutionVersionOutputDto $output): InstitutionVersionResultDto
    {
        return new InstitutionVersionResultDto(
            id: $output->id,
            number: $output->number,
            kind: $output->kind,
            status: $output->status,
            lines: $output->lines,
            files: $output->files,
            prints: $output->prints,
            generation: $output->generation,
            groups: $output->groups,
            error: $output->error,
            bytes: $output->bytes,
            createdAt: (string)$this->moment($output->createdAt),
            readyAt: $this->moment($output->readyAt),
            expiresAt: $this->moment($output->expiresAt),
        );
    }

    public function download(InstitutionDownloadOutputDto $output): InstitutionDownloadResultDto
    {
        return new InstitutionDownloadResultDto('/api/v1/public/production/archives/institution/' . $output->ticket, (string)$this->moment($output->expiresAt));
    }

    private function moment(?\DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new \DateTimeZone('Europe/Moscow'))->format(\DateTimeInterface::ATOM);
    }
}
