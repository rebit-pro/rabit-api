<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\File;

use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class PhotoFileInspector
{
    private const int MAX_BYTES = 26214400;
    private const int MAX_PIXELS = 40000000;
    private const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function inspect(
        string $tmpName,
        string $filename,
        int $bytes,
        ?string $clientFingerprint,
    ): InspectedPhoto {
        if (1 > $bytes || self::MAX_BYTES < $bytes || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new HttpException('INVALID_PHOTO_SIZE', 422);
        }
        $actualBytes = filesize($tmpName);
        if (false === $actualBytes || $actualBytes !== $bytes) {
            throw new HttpException('INVALID_PHOTO_SIZE', 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if (!is_string($mime) || !in_array($mime, self::MIME_TYPES, true)) {
            throw new HttpException('UNSUPPORTED_PHOTO_FORMAT', 422);
        }
        $dimensions = getimagesize($tmpName);
        if (!is_array($dimensions) || 1 > (int)$dimensions[0] || 1 > (int)$dimensions[1]) {
            throw new HttpException('CORRUPTED_PHOTO', 422);
        }
        $width = (int)$dimensions[0];
        $height = (int)$dimensions[1];
        if (self::MAX_PIXELS < $width * $height) {
            throw new HttpException('PHOTO_TOO_LARGE', 422);
        }
        $fingerprint = hash_file('sha256', $tmpName);
        if (!is_string($fingerprint)) {
            throw new HttpException('PHOTO_HASH_FAILED', 503);
        }
        if (null !== $clientFingerprint
            && (1 !== preg_match('/^[a-f0-9]{64}$/D', $clientFingerprint)
                || !hash_equals($fingerprint, strtolower($clientFingerprint)))) {
            throw new HttpException('FINGERPRINT_MISMATCH', 422);
        }
        $safeName = trim(basename(str_replace('\\', '/', $filename)));
        if ('' === $safeName || 255 < mb_strlen($safeName)) {
            throw new HttpException('INVALID_FILENAME', 422);
        }

        return new InspectedPhoto(
            tmpName: $tmpName,
            filename: $safeName,
            mimeType: $mime,
            bytes: $bytes,
            width: $width,
            height: $height,
            fingerprint: $fingerprint,
        );
    }
}
