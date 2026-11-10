<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;

/**
 * Передаёт исходные имена кадров для неизменяемого производственного снимка, даже если оригинал ещё не готов или утрачен.
 * Читает только метаданные ограниченными пачками, не подменяя имя кодом кадра или адресом файла.
 */
final readonly class PhotoFilenames implements PhotoFilenamesInterface
{
    private const int BATCH_SIZE = 500;

    public function __construct(private PhotoRepository $photos) {}

    public function filenames(array $photoIds): array
    {
        $ids = [];
        foreach ($photoIds as $id) {
            if (1 === preg_match('/^[a-f0-9-]{36}$/D', $id)) {
                $ids[$id] = $id;
            }
        }
        $names = [];
        foreach (array_chunk(array_values($ids), self::BATCH_SIZE) as $batch) {
            $result = $this->photos->filenames($batch);
            while (false !== ($row = $result->fetch())) {
                /** @var array{
                 *     UF_PUBLIC_ID: string,
                 *     UF_FILENAME: string,
                 * } $row */
                $names[$row['UF_PUBLIC_ID']] = $row['UF_FILENAME'];
            }
        }

        return $names;
    }
}
