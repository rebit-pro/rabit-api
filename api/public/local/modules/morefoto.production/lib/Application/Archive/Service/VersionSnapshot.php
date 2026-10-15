<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Archive\ValueObject\NewVersion;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Фиксирует состав новой версии архива из оплаченной до срока печати группы. Версия 1 берёт всю оплаченную печать,
 * дополнение — только строки, не вошедшие в прежние версии (L1-DEC-02); пустая версия 1 фиксирует «нет оплаченной
 * печати» без ZIP. Заказы, ждущие подтверждения оплаты, только считаются: подтвердившись до срока, они уйдут в дополнение.
 */
final readonly class VersionSnapshot
{
    public function __construct(
        private ArchiveRepositoryInterface $archives,
        private GroupPrintOrdersInterface $orders,
        private ArchiveLayout $layout,
        private ArchiveStorageInterface $storage,
        private ProductionIdGeneratorInterface $ids,
    ) {}

    /**
     * @param bool $firstOnly планировщик создаёт только версию 1; дополнения собирает организатор
     *
     * @return null|ArchiveVersion null — версию уже создал параллельный процесс или планировщику нечего делать
     *
     * @throws HttpException NOTHING_TO_BUILD — у организатора нет новых оплаченных строк для дополнения
     */
    public function take(GroupDirectoryItemOutputDto $group, \DateTimeImmutable $deadline, ?int $actorId, \DateTimeImmutable $now, bool $firstOnly): ?ArchiveVersion
    {
        $number = $this->archives->nextNumber($group->nativeId, $deadline);
        if ($firstOnly && 1 < $number) {
            return null;
        }
        $included = array_flip($this->archives->includedLines($group->nativeId, $deadline));
        $lines = [];
        foreach ($this->orders->paidPrintLines($group->id, $deadline) as $paid) {
            if (isset($included[$paid->lineId])) {
                continue;
            }
            $lines[] = new ArchiveLine(
                orderLineId: $paid->lineId,
                orderId: $paid->orderId,
                orderNumber: $paid->orderNumber,
                paidAt: $paid->paidAt,
                audience: $paid->audience,
                childCode: $paid->childCode,
                photoId: $paid->photoId,
                photoCode: $paid->photoCode,
                productId: $paid->productId,
                productName: $paid->productName,
                productFormat: $paid->productFormat,
                printCount: $paid->printCount,
                quantity: $paid->quantity,
                prints: $paid->quantity * $paid->printCount,
                entry: '',
            );
        }
        if (1 < $number && [] === $lines) {
            throw new HttpException('NOTHING_TO_BUILD', 409);
        }
        $lines = $this->layout->arrange($lines);
        $files = [];
        $prints = 0;
        foreach ($lines as $line) {
            $files[$line->entry] = true;
            $prints += $line->prints;
        }
        $id = $this->ids->uuid();
        $created = $this->archives->create(new NewVersion(
            publicId: $id,
            groupId: $group->nativeId,
            groupPublicId: $group->id,
            institutionId: $group->institutionNativeId,
            deadlineAt: $deadline,
            number: $number,
            status: [] === $lines ? VersionStatusEnum::EMPTY : VersionStatusEnum::PENDING,
            filesCount: count($files),
            printsCount: $prints,
            waitingOrders: $this->orders->waitingPrintOrders($group->id),
            archivePath: $this->storage->archivePath($group->id, $id),
            lines: $lines,
            createdAt: $now,
            createdBy: $actorId,
        ));

        return $created ? $this->archives->find($id) : null;
    }
}
