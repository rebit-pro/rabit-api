<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Avatar;

use Morefoto\Access\Application\Avatar\Contract\AvatarInspectorInterface;
use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Checks an uploaded avatar by its content, not by the client's name or type, before GD decodes it. */
final readonly class AvatarFileInspector implements AvatarInspectorInterface
{
    private const int MAX_BYTES = 5242880;
    private const int MAX_PIXELS = 25000000;
    private const int MIN_SIDE = 64;
    private const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function inspect(UploadedAvatarInputDto $upload): InspectedAvatarDto
    {
        $bytes = is_file($upload->tmpName) && is_readable($upload->tmpName) ? filesize($upload->tmpName) : false;
        if (false === $bytes || 1 > $bytes) {
            throw new HttpException('CORRUPTED_AVATAR', 422);
        }
        if (self::MAX_BYTES < $bytes) {
            throw new HttpException('AVATAR_TOO_LARGE', 422);
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->tmpName);
        if (!is_string($mimeType) || !in_array($mimeType, self::MIME_TYPES, true)) {
            throw new HttpException('UNSUPPORTED_AVATAR_FORMAT', 422);
        }
        $size = @getimagesize($upload->tmpName);
        if (!is_array($size) || 1 > (int)$size[0] || 1 > (int)$size[1]) {
            throw new HttpException('CORRUPTED_AVATAR', 422);
        }
        [$width, $height] = [(int)$size[0], (int)$size[1]];
        if (self::MAX_PIXELS < $width * $height) {
            throw new HttpException('AVATAR_TOO_LARGE', 422);
        }
        if (self::MIN_SIDE > $width || self::MIN_SIDE > $height) {
            throw new HttpException('AVATAR_TOO_SMALL', 422);
        }
        $fingerprint = hash_file('sha256', $upload->tmpName);
        if (!is_string($fingerprint)) {
            throw new HttpException('CORRUPTED_AVATAR', 422);
        }

        return new InspectedAvatarDto(
            tmpName: $upload->tmpName,
            mimeType: $mimeType,
            bytes: $bytes,
            width: $width,
            height: $height,
            fingerprint: $fingerprint,
        );
    }
}
