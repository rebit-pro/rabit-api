<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\ValueObject;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;

/** Сохранённая версия архива печати группы: неизменяемый состав и состояние его файла. */
final readonly class ArchiveVersion
{
    /**
     * @param int                     $groupId        внутренний ID группы Organization
     * @param int                     $institutionId  внутренний ID учреждения — область чтения сотрудника
     * @param \DateTimeImmutable      $deadlineAt     срок печати группы, UTC
     * @param int                     $waitingOrders  заказы с печатью, ждавшие подтверждения оплаты в момент снимка
     * @param int                     $linkGeneration поколение ссылки; перевыпуск увеличивает его и отзывает прежнюю
     * @param null|\DateTimeImmutable $expiresAt      конец хранения файла и действия ссылки, UTC
     * @param null|int                $createdBy      сотрудник, собравший версию вручную; null — автоматическая сборка
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public int $groupId,
        public string $groupPublicId,
        public int $institutionId,
        public \DateTimeImmutable $deadlineAt,
        public int $number,
        public VersionStatusEnum $status,
        public int $linesCount,
        public int $filesCount,
        public int $printsCount,
        public int $waitingOrders,
        public string $archivePath,
        public ?int $bytes,
        public ?string $errorCode,
        public int $attempts,
        public int $linkGeneration,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?int $createdBy,
    ) {}
}
