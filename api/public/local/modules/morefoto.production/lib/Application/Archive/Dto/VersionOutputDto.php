<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class VersionOutputDto
{
    /**
     * @param string                 $groupId       публичный ID группы
     * @param int                    $number        1 — архив группы, больше 1 — дополнение
     * @param string                 $status        pending | ready | failed | empty | expired
     * @param int                    $waitingOrders заказы с печатью, ждавшие подтверждения оплаты в момент снимка
     * @param list<ProductOutputDto> $products
     * @param bool                   $manual        версию собрал организатор кнопкой
     * @param null|string            $archiveUrl    ссылка без входа на ZIP; только у готовой версии в срок хранения
     */
    public function __construct(
        public string $id,
        public string $groupId,
        public int $number,
        public string $status,
        public int $lines,
        public int $files,
        public int $prints,
        public int $waitingOrders,
        public array $products,
        public bool $manual,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?int $bytes,
        public ?string $errorCode,
        public ?string $archiveUrl,
    ) {}
}
