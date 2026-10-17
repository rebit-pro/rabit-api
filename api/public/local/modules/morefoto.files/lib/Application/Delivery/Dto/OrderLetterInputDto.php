<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Dto;

final readonly class OrderLetterInputDto
{
    /**
     * @param int          $number      номер письма, с 1
     * @param int          $letters     всего писем отправки
     * @param list<string> $attachments имена кадров во вложении этого письма
     * @param int          $first       порядковый номер первого кадра письма среди всех кадров отправки, с 1
     * @param int          $photos      всего кадров отправки, включая кадры только по ссылке
     * @param list<string> $linkOnly    имена кадров выше предела письма
     */
    public function __construct(
        public int $number,
        public int $letters,
        public array $attachments,
        public int $first,
        public int $photos,
        public array $linkOnly,
    ) {}
}
