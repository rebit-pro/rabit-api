<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\File;

use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;

/**
 * CSV для Excel с русской локалью: UTF-8 с BOM, разделитель `;`, строки CRLF. Значения, которые табличный редактор
 * принял бы за формулу, начинаются с апострофа.
 */
final readonly class CsvManifestWriter implements ManifestWriterInterface
{
    private const array HEADER = ['Продукция', 'Формат', 'Файл', 'Кадр', 'Ребёнок', 'Заказ', 'Оплачен (МСК)', 'Количество',
        'Отпечатков в единице', 'Отпечатков', 'Группа сотрудников'];
    private const string TIMEZONE = 'Europe/Moscow';

    public function csv(array $lines, array $extensions): string
    {
        $rows = [$this->row(self::HEADER)];
        foreach ($lines as $line) {
            $paidAt = new \DateTimeImmutable($line->paidAt, new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $rows[] = $this->row([
                $line->productName,
                $line->productFormat,
                $line->entry . '.' . ($extensions[$line->photoId] ?? 'jpg'),
                $line->photoCode,
                $line->childCode,
                $line->orderNumber,
                $paidAt->format('d.m.Y H:i'),
                (string)$line->quantity,
                (string)$line->printCount,
                (string)$line->prints,
                'staff' === $line->audience ? 'да' : 'нет',
            ]);
        }

        return "\u{FEFF}" . implode("\r\n", $rows) . "\r\n";
    }

    /** @param list<string> $values */
    private function row(array $values): string
    {
        return implode(';', array_map($this->cell(...), $values));
    }

    private function cell(string $value): string
    {
        if ('' !== $value && 1 === preg_match('/^[=+\-@\t\r]/', $value) && 1 !== preg_match('/^-?\d+$/D', $value)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
