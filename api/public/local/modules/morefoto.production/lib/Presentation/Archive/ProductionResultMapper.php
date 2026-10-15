<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive;

use Morefoto\Production\Application\Archive\Dto\GroupProductionOutputDto;
use Morefoto\Production\Application\Archive\Dto\GroupProductionStatusOutputDto;
use Morefoto\Production\Application\Archive\Dto\ProductOutputDto;
use Morefoto\Production\Application\Archive\Dto\VersionOutputDto;
use Morefoto\Production\Presentation\Archive\Result\Dto\GroupProductionResultDto;
use Morefoto\Production\Presentation\Archive\Result\Dto\GroupProductionStatusResultDto;
use Morefoto\Production\Presentation\Archive\Result\Dto\ProductResultDto;
use Morefoto\Production\Presentation\Archive\Result\Dto\VersionResultDto;

/** Даты — по Москве в ATOM, как в остальных ответах кабинета; ссылки на архив и CSV относительны к сайту. */
final readonly class ProductionResultMapper
{
    private const string TIMEZONE = 'Europe/Moscow';

    /**
     * @param list<GroupProductionStatusOutputDto> $items
     *
     * @return list<GroupProductionStatusResultDto>
     */
    public function groups(array $items): array
    {
        return array_map(static fn(GroupProductionStatusOutputDto $item): GroupProductionStatusResultDto => new GroupProductionStatusResultDto(
            $item->groupId,
            $item->state,
            $item->version,
        ), $items);
    }

    public function group(GroupProductionOutputDto $output): GroupProductionResultDto
    {
        return new GroupProductionResultDto(
            groupId: $output->groupId,
            groupName: $output->groupName,
            state: $output->state,
            deadlineAt: $this->moment($output->deadlineAt),
            buildFrom: $this->moment($output->buildFrom),
            canManage: $output->canManage,
            outsideLines: $output->outsideLines,
            outsidePrints: $output->outsidePrints,
            waitingOrders: $output->waitingOrders,
            versions: array_map($this->version(...), $output->versions),
        );
    }

    public function version(VersionOutputDto $output): VersionResultDto
    {
        return new VersionResultDto(
            id: $output->id,
            groupId: $output->groupId,
            number: $output->number,
            status: $output->status,
            lines: $output->lines,
            files: $output->files,
            prints: $output->prints,
            waitingOrders: $output->waitingOrders,
            products: array_map(static fn(ProductOutputDto $product): ProductResultDto => new ProductResultDto(
                $product->name,
                $product->format,
                $product->files,
                $product->prints,
            ), $output->products),
            manual: $output->manual,
            createdAt: (string)$this->moment($output->createdAt),
            readyAt: $this->moment($output->readyAt),
            expiresAt: $this->moment($output->expiresAt),
            bytes: $output->bytes,
            error: $output->errorCode,
            archiveUrl: $output->archiveUrl,
            exportUrl: '/api/v1/production/versions/' . $output->id . '/export',
        );
    }

    private function moment(?\DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(\DateTimeInterface::ATOM);
    }
}
