<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Sales\Pdf;

use Morefoto\Commerce\Application\Sales\Dto\WorksheetColumnOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetLineOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOrderOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOutputDto;

/** Разметка готовой ведомости с исходными файлами: форматирование без расчёта покупок и путей архива. */
final readonly class WorksheetHtml
{
    private const int INLINE_FILES = 3;
    private const int COLUMNS_PER_BLOCK = 3;
    private const int DIGITAL_FRAMES_PER_ROW = 6;

    public function css(): string
    {
        return <<<'CSS'
            .worksheet { font-size: 8pt; }
            .worksheet .version { padding: 3mm; background: #EEF5FA; margin-bottom: 4mm; }
            .worksheet table.rows { table-layout: fixed; font-size: 8pt; }
            .worksheet table.rows th, .worksheet table.rows td { padding: 2mm 1.5mm; vertical-align: top; }
            .worksheet thead { display: table-header-group; }
            .worksheet .path { font-size: 7pt; color: #5E6872; word-wrap: break-word; }
            .worksheet .file { word-wrap: break-word; margin-bottom: 1.5mm; }
            .worksheet .meta { font-size: 7pt; color: #5E6872; }
            .worksheet .warning { color: #6B4E16; }
            CSS;
    }

    public function body(WorksheetOutputDto $report): string
    {
        $final = 'final' === $report->mode;
        // Фиксированные ширины справа сохраняют позицию электронной колонки во всех группах.
        $electronicWidth = 0 === $report->prints ? '60%' : '45%';
        $html = '<div class="worksheet"><h1>Ведомость по заказам и файлам печати</h1><div class="version">';
        $archive = $report->archive;
        if (null === $archive) {
            $html .= '<strong>Предварительная — не задание лаборатории</strong>';
        } else {
            $html .= '<strong>Окончательная · ' . ('institution' === $archive->type ? 'Учреждение' : 'Группа')
                . ' · версия ' . $archive->number . ' · ' . ('supplement' === $archive->kind ? 'Дополнение' : 'Полный выпуск') . '</strong>'
                . '<div class="meta">ID: ' . $this->escape($archive->id) . ' · Снимок: ' . $this->moment($archive->createdAt) . ' (МСК)</div>'
                . '<div class="meta">' . $archive->files . ' файлов · ' . $archive->prints . ' отпечатков в выбранном архиве.</div>';
            if ('institution' === $archive->type && 'full' === $archive->kind) {
                $html .= '<div class="warning">Накопительный состав: прежние задания и новые. Скачивание не является новым заданием печати.</div>';
            }
        }
        $html .= '<div class="meta">Сформировано: ' . $this->moment($report->generatedAt) . ' (МСК). '
            . ($final ? 'Печатные строки и итог относятся только к выбранной версии. Электронные покупки показаны отдельно на момент формирования.'
                : 'Включены подтверждённые покупки. Пути будущего ZIP не вычисляются.') . '</div></div>';
        if ([] === $report->groups) {
            $html .= '<p>В выбранной области пока нет групп.</p>';
        }
        foreach ($report->groups as $group) {
            $html .= '<h2>' . $this->escape($group->institutionName) . ' · ' . $this->escape($group->shootName) . ' · ' . $this->escape($group->name) . '</h2>';
            $used = [];
            foreach ($group->orders as $order) {
                foreach ($order->lines as $line) {
                    if ('physical' === $line->kind && $line->selected) {
                        $used[$line->columnKey] = true;
                    }
                }
            }
            $columns = array_values(array_filter($report->columns, static fn(WorksheetColumnOutputDto $column): bool => isset($used[$column->key])));
            foreach ([] === $columns ? [[]] : array_chunk($columns, self::COLUMNS_PER_BLOCK) as $block => $part) {
                $html .= $this->table($group->orders, $part, $final, 0 === $block, $electronicWidth);
            }
            $html .= '<p><strong>Итого по группе · ' . $group->prints . ' отп. · ' . $this->money($group->total) . '</strong></p>';
            $html .= $this->details($group->orders);
            $html .= $this->outsideOrders($group->outsideOrders);
            if ($final) {
                foreach ($group->orders as $order) {
                    $outside = [];
                    foreach ($order->lines as $line) {
                        if ('physical' === $line->kind && !$line->selected) {
                            $outside[] = $line;
                        }
                    }
                    if ([] !== $outside) {
                        $html .= '<p class="meta">' . $this->escape($order->number) . ' · печать вне выбранной версии: ';
                        foreach ($outside as $line) {
                            $html .= $this->escape($line->productName) . ' — ' . $line->prints . ' отп.; ';
                        }
                        $html .= '</p>';
                    }
                }
            }
        }
        $html .= '<h2>Итого по выбранной области: ' . $this->money($report->total) . ' · ' . $report->prints . ' отп.</h2>'
            . '<p>' . ($final ? 'Отдельно текущие электронные покупки: ' : 'В том числе электронные покупки: ')
            . $this->money($report->electronicTotal) . '.</p><p class="note">'
            . 'ФИО относится к заказу целиком. При нескольких детях соответствие неоднозначно; одинаковые ФИО не объединяют заказы. '
            . 'У общего файла показаны вклад позиции и число отпечатков в файле: их не складывают между собой. '
            . 'J4-копии к печати и подарочные комплекты не увеличивают продажи. Состав комплекта раскрыт по J1 на момент формирования.</p></div>';

        return $html;
    }

    private function name(WorksheetOrderOutputDto $order): string
    {
        $name = null === $order->firstName || null === $order->lastName ? 'Не указано' : $order->lastName . ' ' . $order->firstName;

        return '<strong>' . $this->escape($name) . '</strong><div class="meta">' . $this->escape($order->number)
            . ' · коды: ' . $this->escape(implode(', ', $order->childCodes)) . '</div>'
            . ($order->ambiguous ? '<div class="warning">Несколько детей: ФИО указано для заказа целиком.</div>' : '');
    }

    /**
     * @param list<WorksheetOrderOutputDto>  $orders
     * @param list<WorksheetColumnOutputDto> $columns
     */
    private function table(array $orders, array $columns, bool $final, bool $first, string $electronicWidth): string
    {
        $html = '<table class="rows" autosize="1" style="overflow: wrap"><thead><tr><th width="5%">№</th><th>Ф.И. ребёнка / заказ</th>';
        foreach ($columns as $column) {
            $html .= '<th>' . $this->wrap($column->name) . '<br><span class="meta">' . $this->wrap($column->format) . '</span></th>';
        }
        if ($first) {
            $html .= '<th width="' . $electronicWidth . '">Электронная версия' . ($final ? ' (текущие покупки)' : '')
                . '</th><th class="num" width="12%">'
                . ($final ? 'Итого, печать' : 'Итого') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if ([] === $orders) {
            $html .= '<tr><td colspan="' . (count($columns) + ($first ? 4 : 2)) . '">Оплаченного состава нет.</td></tr>';
        }
        foreach ($orders as $index => $order) {
            $cells = [];
            foreach ($columns as $column) {
                $cell = [];
                foreach ($order->lines as $line) {
                    if ('physical' === $line->kind && $line->selected && $line->columnKey === $column->key) {
                        $cell[] = $this->physical($line);
                    }
                }
                $cells[] = $cell;
            }
            if ($first) {
                $cells[] = $this->electronic($order);
            }
            $rows = 1;
            foreach ($cells as $cell) {
                $rows = max($rows, count($cell));
            }
            for ($row = 0; $row < $rows; ++$row) {
                $html .= '<tr>';
                if (0 === $row) {
                    $html .= '<td>' . ($index + 1) . '</td><td>' . ($first ? $this->name($order) : $this->escape($order->number)) . '</td>';
                } else {
                    $html .= '<td></td><td>' . $this->orderReference($order) . '</td>';
                }
                foreach ($cells as $cell) {
                    $html .= '<td>' . ($cell[$row] ?? '') . '</td>';
                }
                if ($first) {
                    $html .= '<td class="num">';
                    if (0 === $row) {
                        $html .= '<strong>' . $this->money($order->includedTotal) . '</strong>';
                        if ($final && $order->total !== $order->includedTotal) {
                            $html .= '<div class="meta">Весь заказ: ' . $this->money($order->total) . '</div>';
                        }
                        if (0 < $order->discount) {
                            $html .= '<div class="meta">Учтена скидка: ' . $this->money($order->discount) . '</div>';
                        }
                    }
                    $html .= '</td>';
                }
                $html .= '</tr>';
            }
        }

        return $html . '</tbody></table>';
    }

    private function quantity(WorksheetLineOutputDto $line): string
    {
        return 'physical' === $line->kind
            ? $line->prints . ' отп. <span class="meta">(' . $line->quantity . ' × ' . $line->printCount . ')</span>'
            : $line->quantity . ('bundle' === $line->kind ? ' компл.' : ' шт.');
    }

    private function physical(WorksheetLineOutputDto $line): string
    {
        $html = '<div class="file">' . $this->quantity($line);
        if (self::INLINE_FILES < count($line->files)) {
            return $html . '<div>Файлы: ' . count($line->files) . ' · см. детализацию ниже.</div></div>';
        }
        foreach ($line->files as $file) {
            $html .= '<div>' . $this->file($file) . '</div>';
        }

        return $html . ([] === $line->files ? '<span class="warning">Состав файла недоступен</span>' : '') . '</div>';
    }

    /** @return list<string> */
    private function electronic(WorksheetOrderOutputDto $order): array
    {
        $cells = $this->items($order, includePhysical: false);
        if ([] !== $order->giftCodes) {
            $cells[] = '<div class="meta">Подарочные комплекты: ' . $this->wrap(implode(', ', $order->giftCodes)) . ' (не продажа).</div>';
        }

        return [] === $cells ? ['—'] : $cells;
    }

    /** @return list<string> */
    private function items(WorksheetOrderOutputDto $order, bool $includePhysical): array
    {
        $frames = [];
        $other = [];
        foreach ($order->lines as $line) {
            if ('digital' === $line->kind) {
                $frames[] = $this->digital($line);
            } elseif ($includePhysical || 'bundle' === $line->kind) {
                $other[] = $this->item($line);
            }
        }
        // mPDF переносит таблицу между строками, поэтому длинный перечень делится на несколько ячеек.
        $cells = [];
        foreach (array_chunk($frames, self::DIGITAL_FRAMES_PER_ROW) as $part) {
            $cells[] = implode(', ', $part);
        }

        return array_merge($cells, $other);
    }

    private function digital(WorksheetLineOutputDto $line): string
    {
        $file = $line->files[0] ?? null;
        $html = null === $file ? '<span class="warning">Кадр недоступен</span>' : $this->file($file, compact: true);

        return $html . (1 === $line->quantity ? '' : '&nbsp;×&nbsp;' . $line->quantity)
            . ($line->coveredByGift ? ' · подарок, не продажа' : '');
    }

    private function item(WorksheetLineOutputDto $line): string
    {
        $html = '<div class="file">' . $this->wrap($line->productName) . ' — ' . $this->quantity($line)
            . ($line->coveredByGift ? ' · подарок, не продажа' : '');
        if (self::INLINE_FILES < count($line->files)) {
            return $html . '<div>Файлы: ' . count($line->files) . ' · см. детализацию ниже.</div></div>';
        }
        foreach ($line->files as $file) {
            $html .= '<div>' . $this->file($file) . '</div>';
        }

        return $html . ([] === $line->files ? '<div class="warning">Состав файлов недоступен</div>' : '') . '</div>';
    }

    private function file(WorksheetFileOutputDto $file, bool $compact = false): string
    {
        $html = $this->wrap($file->filename ?? 'Исходное имя недоступно');
        if (null !== $file->code) {
            $frame = $this->wrap($file->code);
            $child = null === $file->childCode ? '' : $this->wrap($file->childCode);
            $html .= $compact
                ? ' <span class="meta">(' . $frame . ')</span>'
                : '<span class="meta"> · Кадр ' . $frame . ('' === $child ? '' : ' · ребёнок ' . $child) . '</span>';
        }
        if (null !== $file->path) {
            $html .= '<div class="path">' . $this->wrap($file->path) . '</div><div class="meta">В файле: ' . $file->printsInFile . ' отп.</div>';
        }

        return $html;
    }

    /** @param list<WorksheetOrderOutputDto> $orders */
    private function details(array $orders): string
    {
        $html = '';
        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                if (self::INLINE_FILES >= count($line->files)) {
                    continue;
                }
                $html .= '<h2>Файлы · ' . $this->escape($order->number) . ' · ' . $this->wrap($line->productName) . '</h2>'
                    . '<p class="meta">Позиция: ' . $this->wrap($line->id) . ' · ' . $this->quantity($line) . '</p>';
                // Normal flow can break between files and within long names without table font shrinking.
                foreach ($line->files as $index => $file) {
                    $html .= '<div class="file">' . ($index + 1) . '. ' . $this->escape($order->number) . ' · ' . $this->file($file) . '</div>';
                }
            }
        }

        return $html;
    }

    /** @param list<WorksheetOrderOutputDto> $orders */
    private function outsideOrders(array $orders): string
    {
        if ([] === $orders) {
            return '';
        }
        $html = '<h2>Текущие заказы вне выбранной печатной версии</h2><table class="rows" autosize="1"><thead><tr><th>Заказ / ФИО</th><th>Состояние оплаты</th><th>Состав снимка</th><th class="num">Сумма заказа</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            foreach ($this->items($order, includePhysical: true) as $index => $item) {
                $html .= '<tr><td>' . (0 === $index ? $this->name($order) : $this->orderReference($order)) . '</td><td>'
                    . (0 === $index ? $this->escape($order->latePayment ? 'Поздняя оплата' : match ($order->paymentStatus) {
                        'paid' => 'Оплачен', 'pending' => 'Ожидает оплаты', 'declined' => 'Оплата отклонена', default => 'Не оплачен',
                    }) : '') . '</td><td>' . $item . '</td><td class="num">'
                    . (0 === $index ? $this->money($order->total) : '') . '</td></tr>';
            }
        }

        return $html . '</tbody></table>' . $this->details($orders);
    }

    private function orderReference(WorksheetOrderOutputDto $order): string
    {
        return '<span class="meta">' . $this->escape($order->number) . '</span>';
    }

    /** Zero-width break opportunities preserve every filename/path character in narrow PDF columns. */
    private function wrap(string $value): string
    {
        return implode('&#8203;', array_map($this->escape(...), mb_str_split($value, 12)));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function money(int $kopecks): string
    {
        $rubles = number_format(intdiv($kopecks, 100), 0, ',', "\u{00A0}");
        $rest = $kopecks % 100;

        return $rubles . (0 === $rest ? '' : ',' . sprintf('%02d', $rest)) . "\u{00A0}₽";
    }

    private function moment(string $value): string
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i');
    }
}
