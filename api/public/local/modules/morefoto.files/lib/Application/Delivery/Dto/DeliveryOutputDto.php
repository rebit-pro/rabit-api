<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class DeliveryOutputDto
{
    /**
     * @param string                  $kind            auto | manual
     * @param string                  $status          pending | accepted | failed | cancelled; accepted — все письма переданы почтовому серверу, не прочитаны
     * @param null|\DateTimeImmutable $acceptedAt      когда почтовый сервер принял последнее письмо
     * @param int                     $letters         писем по плану; 0 — план ещё не записан или отправка отменена
     * @param int                     $acceptedLetters писем, принятых почтовым сервером
     * @param int                     $photos          кадров во вложениях всех писем
     * @param int                     $linkOnly        кадров выше предела письма — только по ссылке
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public string $recipient,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $acceptedAt,
        public int $letters,
        public int $acceptedLetters,
        public int $photos,
        public int $linkOnly,
    ) {}
}
