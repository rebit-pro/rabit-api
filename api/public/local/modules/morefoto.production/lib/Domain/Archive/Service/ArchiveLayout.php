<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Service;

use Morefoto\Production\Domain\Archive\Exception\ArchiveLayoutException;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;

/**
 * Превращает оплаченные строки группы в задания по фото и продукции, суммируя все оплаченные отпечатки общего кадра.
 * Сохраняет исходный basename; количество задаёт папка Nшт, а коллизии различаются каталогами идентичности фото.
 */
final readonly class ArchiveLayout
{
    public const int VERSION = 2;
    private const int MAX_FOLDER_LENGTH = 80;
    private const string DEFAULT_FOLDER = 'Продукция';
    private const string COLLISIONS = '_фото';

    /**
     * @param list<ArchiveLine>     $lines     строки только одной версии группы; поле entry не используется
     * @param array<string, string> $filenames исходные имена по photoId
     *
     * @return list<ArchiveLine> те же строки с полным путём файла, включая исходное расширение
     *
     * @throws ArchiveLayoutException неизвестное/небезопасное имя или некорректный состав; снимок не должен записываться
     */
    public function arrange(array $lines, array $filenames): array
    {
        $products = [];
        $prints = [];
        foreach ($lines as $line) {
            $this->identity($line->productId);
            $this->identity($line->photoId);
            $this->filename($filenames[$line->photoId] ?? '');
            if (0 >= $line->quantity || 0 >= $line->printCount || $line->prints !== $line->quantity * $line->printCount) {
                throw new ArchiveLayoutException('INVALID_ARCHIVE_LAYOUT');
            }
            if (!isset($products[$line->productId]) || strcmp($line->productName, $products[$line->productId]) < 0) {
                $products[$line->productId] = $line->productName;
            }
            $key = $this->fileKey($line);
            $total = $prints[$key] ?? 0;
            if (PHP_INT_MAX - $total < $line->prints) {
                throw new ArchiveLayoutException('INVALID_ARCHIVE_LAYOUT');
            }
            $prints[$key] = $total + $line->prints;
        }
        $folders = $this->folders($products);
        $buckets = [];
        foreach ($lines as $line) {
            $bucket = $this->bucket($line, $prints[$this->fileKey($line)]);
            $buckets[$bucket][$this->canonical($filenames[$line->photoId])][$line->photoId] = true;
        }
        $arranged = [];
        foreach ($lines as $line) {
            $count = $prints[$this->fileKey($line)];
            $name = $filenames[$line->photoId];
            $canonical = $this->canonical($name);
            $directory = $folders[$line->productId] . (1 === $count ? '' : '/' . $count . 'шт');
            if (1 < count($buckets[$this->bucket($line, $count)][$canonical]) || self::COLLISIONS === $canonical
                || 1 === preg_match('/^[1-9][0-9]*шт$/uD', $canonical)) {
                $directory .= '/' . self::COLLISIONS . '/' . $line->photoId;
            }
            $entry = $directory . '/' . $name;
            if (512 < mb_strlen($entry)) {
                throw new ArchiveLayoutException('UNSAFE_FILENAME');
            }
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
                entry: $entry,
            );
        }

        return $arranged;
    }

    /**
     * Имена каталогов по устойчивому ID: то же правило для продукции, учреждения и групп.
     *
     * @param array<string, string> $names
     *
     * @return array<string, string>
     */
    public function folders(array $names, string $fallback = self::DEFAULT_FOLDER): array
    {
        ksort($names, SORT_STRING);
        $folders = [];
        // Состав в корне — файл: одноимённый каталог должен получить другое имя.
        $taken = [$this->canonical('состав.csv') => true];
        foreach ($names as $id => $name) {
            $this->identity((string)$id);
            $base = $this->folder($name, $fallback);
            $folder = $base;
            if (isset($taken[$this->canonical($folder)])) {
                $folder = $this->withSuffix($base, ' (' . $id . ')');
            }
            for ($suffix = 2; isset($taken[$this->canonical($folder)]); ++$suffix) {
                $folder = $this->withSuffix($base, ' (' . $id . ') ' . $suffix);
            }
            $taken[$this->canonical($folder)] = true;
            $folders[$id] = $folder;
        }

        return $folders;
    }

    private function filename(string $name): void
    {
        if ('' === $name) {
            throw new ArchiveLayoutException('ORIGINAL_MISSING');
        }
        if (!mb_check_encoding($name, 'UTF-8') || 255 < strlen($name) || rtrim($name, ' .') !== $name
            || str_contains($name, '\\') || 1 === preg_match('~[\p{Cc}\p{Cf}/:*?"<>|]~u', $name)
            || 1 === preg_match('/^(?:CON|PRN|AUX|NUL|CONIN\$|CONOUT\$|COM[1-9¹²³]|LPT[1-9¹²³])(?: *\.|$)/ui', $name)) {
            throw new ArchiveLayoutException('UNSAFE_FILENAME');
        }
    }

    /** Очищает имя каталога; исходные имена файлов не преобразуются. */
    private function folder(string $name, string $fallback): string
    {
        $safe = (string)preg_replace('~[\p{Cc}\p{Cf}/\\\:*?"<>|]+~u', ' ', $name);
        $safe = trim((string)preg_replace('/\s+/u', ' ', $safe), ' .');
        $safe = rtrim(mb_substr($safe, 0, self::MAX_FOLDER_LENGTH), ' .');
        if ('' === $safe || 1 === preg_match('/^[=+\-@]/', $safe) || 1 === preg_match('/^(?:CON|PRN|AUX|NUL|CONIN\$|CONOUT\$|COM[1-9¹²³]|LPT[1-9¹²³])(?: *\.|$)/ui', $safe)) {
            $safe = $fallback . ('' === $safe ? '' : ' ' . $safe);
        }

        return rtrim(mb_strcut($safe, 0, 255, 'UTF-8'), ' .');
    }

    private function withSuffix(string $base, string $suffix): string
    {
        return rtrim(mb_strcut($base, 0, 255 - strlen($suffix), 'UTF-8'), ' .') . $suffix;
    }

    private function canonical(string $value): string
    {
        return mb_convert_case((string)\Normalizer::normalize($value, \Normalizer::FORM_C), MB_CASE_FOLD, 'UTF-8');
    }

    private function identity(string $id): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id)) {
            throw new ArchiveLayoutException('INVALID_ARCHIVE_LAYOUT');
        }
    }

    private function fileKey(ArchiveLine $line): string
    {
        return $line->productId . '|' . $line->photoId;
    }

    private function bucket(ArchiveLine $line, int $prints): string
    {
        return $line->productId . '|' . $prints;
    }
}
