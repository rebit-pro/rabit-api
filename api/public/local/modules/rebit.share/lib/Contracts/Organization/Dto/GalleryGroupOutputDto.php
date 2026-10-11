<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GalleryGroupOutputDto
{
    /**
     * @param null|string $sentAt            момент передачи ссылки (T0), UTC `Y-m-d H:i:s`
     * @param null|string $closesAt          окончание приёма печати, UTC `Y-m-d H:i:s`
     * @param null|string $digitalSalesUntil  окончание продажи электронных фото, UTC `Y-m-d H:i:s`
     * @param null|string $markupCorrectionAt начало исправления разметки (D4), UTC `Y-m-d H:i:s`; пока не null, продажа на паузе
     */
    public function __construct(
        public int $id,
        public int $shootId,
        public string $publicId,
        public string $institutionName,
        public string $shootName,
        public string $groupName,
        public string $kind,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $digitalSalesUntil,
        public int $revision,
        public ?string $markupCorrectionAt = null,
    ) {}
}
