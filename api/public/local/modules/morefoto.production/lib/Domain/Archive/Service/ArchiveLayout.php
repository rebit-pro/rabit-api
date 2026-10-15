<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Service;

use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;

/**
 * Раскладывает оплаченную печать версии по папкам продукции из снимка заказа (L1-DEC-05): один файл на кадр ребёнка
 * в папке продукции с суммой отпечатков в имени — `Печать 10×15/A001_x3`. Код кадра Media уже содержит код ребёнка,
 * поэтому общий кадр двух детей даёт два разных файла. Одинаковые названия разной продукции различает её ID.
 */
final readonly class ArchiveLayout
{
    private const int MAX_FOLDER_LENGTH = 80;
    private const string DEFAULT_FOLDER = 'Продукция';

    /**
     * @param list<ArchiveLine> $lines строки версии в порядке заказа; поле entry не используется
     *
     * @return list<ArchiveLine> те же строки с путём файла внутри архива
     */
    public function arrange(array $lines): array
    {
        $folders = [];
        $taken = [];
        foreach ($lines as $line) {
            if (isset($folders[$line->productId])) {
                continue;
            }
            $folder = $this->folder($line->productName);
            if (isset($taken[mb_strtolower($folder)])) {
                $folder = $this->folder($line->productName . ' (' . $line->productId . ')');
            }
            for ($base = $folder, $suffix = 2; isset($taken[mb_strtolower($folder)]); ++$suffix) {
                $folder = $base . ' ' . $suffix;
            }
            $taken[mb_strtolower($folder)] = true;
            $folders[$line->productId] = $folder;
        }
        $prints = [];
        foreach ($lines as $line) {
            $key = $this->fileKey($line);
            $prints[$key] = ($prints[$key] ?? 0) + $line->prints;
        }
        $arranged = [];
        foreach ($lines as $line) {
            $arranged[] = new ArchiveLine(
                orderLineId: $line->orderLineId,
                orderId: $line->orderId,
                orderNumber: $line->orderNumber,
                paidAt: $line->paidAt,
                audience: $line->audience,
                childCode: $line->childCode,
                photoId: $line->photoId,
                photoCode: $line->photoCode,
                productId: $line->productId,
                productName: $line->productName,
                productFormat: $line->productFormat,
                printCount: $line->printCount,
                quantity: $line->quantity,
                prints: $line->prints,
                entry: $folders[$line->productId] . '/' . $this->code($line) . '_x' . $prints[$this->fileKey($line)],
            );
        }

        return $arranged;
    }

    /** Имя папки без разделителей пути, управляющих и запрещённых в Windows символов, без точек по краям. */
    private function folder(string $name): string
    {
        $safe = (string)preg_replace('~[\x00-\x1F\x7F/\\\:*?"<>|]+~u', ' ', $name);
        $safe = trim((string)preg_replace('/\s+/u', ' ', $safe), ' .');
        $safe = rtrim(mb_substr($safe, 0, self::MAX_FOLDER_LENGTH), ' .');

        return '' === $safe ? self::DEFAULT_FOLDER : $safe;
    }

    private function code(ArchiveLine $line): string
    {
        $code = (string)preg_replace('/[^A-Za-z0-9_-]+/', '_', $line->photoCode);

        return '' === trim($code, '_') ? substr($line->photoId, 0, 8) : $code;
    }

    private function fileKey(ArchiveLine $line): string
    {
        return $line->productId . '|' . $line->photoId . '|' . $line->photoCode;
    }
}
