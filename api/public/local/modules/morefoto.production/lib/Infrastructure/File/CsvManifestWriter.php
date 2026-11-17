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

    public function csv(array $lines, array $extensions, int $layoutVersion = 1): string
    {
        if (!in_array($layoutVersion, [1, 2], true)) {
            throw new \InvalidArgumentException('Unknown print archive layout.');
        }
        $header = self::HEADER;
        $prints = [];
        if (2 === $layoutVersion) {
            foreach (['ID строки заказа', 'ID заказа', 'ID фото', 'ID продукции', 'Отпечатков в файле'] as $column) {
                $header[] = $column;
            }
            foreach ($lines as $line) {
                $prints[$line->entry] = ($prints[$line->entry] ?? 0) + $line->prints;
            }
        }
        $rows = [$this->row($header)];
        foreach ($lines as $line) {
            $paidAt = new \DateTimeImmutable($line->paidAt, new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone(self::TIMEZONE));
            $values = [
                $line->productName,
                $line->productFormat,
                1 === $layoutVersion ? $line->entry . '.' . ($extensions[$line->photoId] ?? 'jpg') : $line->entry,
                $line->photoCode,
                $line->childCode,
                $line->orderNumber,
                $paidAt->format('d.m.Y H:i'),
                (string)$line->quantity,
                (string)$line->printCount,
                (string)$line->prints,
                'staff' === $line->audience ? 'да' : 'нет',
            ];
            if (2 === $layoutVersion) {
                $values[] = $line->orderLineId;
                $values[] = $line->orderId;
                $values[] = $line->photoId;
                $values[] = $line->productId;
                $values[] = (string)$prints[$line->entry];
            }
            $rows[] = $this->row($values);
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
