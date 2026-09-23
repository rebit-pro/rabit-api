<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\HttpResponse;
use Rebit\Share\Application\Contract\File\Dto\ImageContentOutputDto;

/**
 * Неизменяемое изображение по адресу с версией: приватный кеш на год, ETag и 304 без тела на совпавший If-None-Match.
 * Адрес меняется вместе с содержимым, поэтому ответ не перепроверяется; общий no-store к нему не добавляется.
 */
final class ImageResponse extends HttpResponse
{
    public const string CACHE_CONTROL = 'private, max-age=31536000, immutable';
    private const int NOT_MODIFIED_CODE = 304;

    /**
     * @throws ArgumentTypeException
     */
    public function __construct(ImageContentOutputDto $image, ?string $ifNoneMatch)
    {
        parent::__construct();
        $etag = EntityTag::quote($image->etag);
        $this->addHeader('ETag', $etag);
        $this->addHeader('Cache-Control', self::CACHE_CONTROL);
        $this->addHeader('X-Content-Type-Options', 'nosniff');
        $this->addHeader('Referrer-Policy', 'no-referrer');
        if (EntityTag::matches($ifNoneMatch, $etag)) {
            $this->setStatus(self::NOT_MODIFIED_CODE);
            $this->setContent(null);

            return;
        }
        $this->addHeader('Content-Type', $image->mimeType);
        $this->setContent($image->content);
    }
}
