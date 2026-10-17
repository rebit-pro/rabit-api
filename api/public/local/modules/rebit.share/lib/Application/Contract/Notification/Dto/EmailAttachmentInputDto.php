<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

/** Вложение письма ссылкой на файл: H1 хранит только эти сведения и читает байты в момент отправки. */
final readonly class EmailAttachmentInputDto
{
    /**
     * @param string $name        имя файла в письме, например `AB003.jpg`
     * @param string $contentType MIME-тип, например `image/jpeg`
     * @param string $path        абсолютный путь файла, который видит потребитель уведомлений
     * @param int    $bytes       размер файла; при отправке он сверяется с файлом на диске
     */
    public function __construct(
        public string $name,
        public string $contentType,
        public string $path,
        public int $bytes,
    ) {}
}
