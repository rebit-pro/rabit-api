<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class GroupProductionOutputDto
{
    /**
     * @param string                  $state         open | scheduled | manual | building | ready | failed | empty | expired
     * @param null|\DateTimeImmutable $deadlineAt    срок приёма печати; null — ссылка группы не передана
     * @param null|\DateTimeImmutable $buildFrom     автоматическая сборка начинается после этого момента
     * @param int                     $outsideLines  оплаченные печатные строки, не вошедшие ни в одну версию
     * @param int                     $waitingOrders заказы с печатью, оплата которых сейчас ждёт подтверждения
     * @param list<VersionOutputDto>  $versions      по возрастанию номера
     */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $state,
        public ?\DateTimeImmutable $deadlineAt,
        public ?\DateTimeImmutable $buildFrom,
        public bool $canManage,
        public int $outsideLines,
        public int $outsidePrints,
        public int $waitingOrders,
        public array $versions,
    ) {}
}
