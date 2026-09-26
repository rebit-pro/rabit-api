<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;

/** Открывает выдаче купленных файлов доступ к приватным оригиналам готовых кадров, не раскрывая устройство хранилища Media.
 * Право покупателя здесь не проверяется: Media отвечает только за то, что кадр готов и его оригинал сохранён.
 */
final readonly class OriginalFiles implements OriginalFilesInterface
{
    public function __construct(private PhotoRepository $photos, private PrivatePhotoStorageInterface $storage) {}

    public function originals(array $photoIds): array
    {
        $ids = array_values(array_unique(array_filter($photoIds, static fn(string $id): bool => 1 === preg_match('/^[a-f0-9-]{36}$/D', $id))));
        if ([] === $ids) {
            return [];
        }
        $originals = [];
        $result = $this->photos->originals($ids);
        while (false !== ($row = $result->fetch())) {
            /** @var array{UF_PUBLIC_ID: string, UF_MIME_TYPE: string, UF_BYTES: int|string, UF_ORIGINAL_PATH: string} $row */
            $originals[$row['UF_PUBLIC_ID']] = new OriginalFileOutputDto(
                photoId: $row['UF_PUBLIC_ID'],
                mimeType: $row['UF_MIME_TYPE'],
                bytes: (int)$row['UF_BYTES'],
                relativePath: $row['UF_ORIGINAL_PATH'],
                absolutePath: $this->storage->absolutePath($row['UF_ORIGINAL_PATH']),
            );
        }

        return $originals;
    }
}
