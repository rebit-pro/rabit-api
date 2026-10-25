<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Sales\Pdf;

use Morefoto\Commerce\Application\Sales\Dto\ChildSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Domain\Sales\Enum\ChildSalesStatusEnum;

/** Разметка фирменного шаблона отчёта: шапка с логотипом, плитки итога, таблица уровня и печать по форматам. */
final readonly class SalesReportHtml
{
    private const string NBSP = "\u{00A0}";

    public function css(): string
    {
        return <<<'CSS'
            body { font-family: golos; font-size: 9pt; color: #1E1E1E; }
            h1 { font-family: manrope; font-weight: bold; font-size: 18pt; margin: 0 0 1mm; color: #1E1E1E; }
            h2 { font-family: manrope; font-weight: bold; font-size: 11pt; margin: 7mm 0 2mm; color: #16405A; }
            .kind { font-size: 8pt; font-weight: bold; color: #24658A; text-transform: uppercase; letter-spacing: 0.3pt; margin: 0 0 1mm; }
            .area { font-size: 10pt; color: #5E6872; margin: 0 0 5mm; }
            .tiles { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin: 0 -2mm; }
            .tile { background: #EEF5FA; border-radius: 2mm; padding: 3mm; vertical-align: top; }
            .tile-value { font-family: manrope; font-weight: bold; font-size: 15pt; color: #16405A; }
            .tile-label { font-size: 8pt; color: #5E6872; }
            table.rows { width: 100%; border-collapse: collapse; }
            table.rows th { font-weight: bold; font-size: 8pt; color: #5E6872; text-align: left; border-bottom: 0.4mm solid #24658A; padding: 1.5mm 1.5mm; }
            table.rows td { border-bottom: 0.2mm solid #DCE4EA; padding: 1.5mm 1.5mm; vertical-align: middle; }
            table.rows .num { text-align: right; white-space: nowrap; }
            table.rows tr.total td { font-weight: bold; border-bottom: 0; border-top: 0.4mm solid #24658A; }
            .code { font-family: manrope; font-weight: bold; font-size: 11pt; }
            .status { font-size: 8pt; font-weight: bold; padding: 0.6mm 1.6mm; border-radius: 1mm; }
            .status-paid { background: #DCEBF4; color: #16405A; }
            .status-late { background: #F3E7D3; color: #6B4E16; }
            .status-unpaid { background: #F6D9D3; color: #8A3A2C; }
            .status-none { background: #EDF1F4; color: #5E6872; }
            .muted { color: #66737F; }
            .note { font-size: 7.5pt; color: #66737F; margin-top: 6mm; }
            .header td { vertical-align: middle; }
            .brand { font-family: manrope; font-weight: bold; font-size: 13pt; color: #1E1E1E; }
            .footer { font-size: 7.5pt; color: #66737F; }
            CSS;
    }

    public function header(): string
    {
        $logo = base64_encode(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
            <path d="M22 11.525 V7 A2.5 2.5 0 0 0 19.5 4.5 H4.5 A2.5 2.5 0 0 0 2 7 V18 A2.5 2.5 0 0 0 4.5 20.5 H19.5 A2.5 2.5 0 0 0 22 18 V15.275" fill="none" stroke="#1E1E1E" stroke-width="1.75" stroke-linejoin="round"/>
            <circle cx="16.5" cy="9" r="1.6" fill="#1E1E1E"/>
            <path d="M0.5 15.2 C5 10.4 8.5 18.6 13 14 S20.5 10.6 23.5 15.4" fill="none" stroke="#24658A" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            SVG);

        return '<table class="header" width="100%"><tr>'
            . '<td width="9mm"><img src="data:image/svg+xml;base64,' . $logo . '" width="8mm" height="8mm"></td>'
            . '<td class="brand">Море <span style="color: #24658A; font-weight: normal">фото</span></td>'
            . '<td class="muted" style="text-align: right">Отчёт о продажах</td>'
            . '</tr></table>';
    }

    public function footer(SalesReportOutputDto $report): string
    {
        return '<table class="footer" width="100%"><tr><td>morefoto36.ru · Сформировано '
            . $this->escape($this->moment($report->generatedAt)) . ' (МСК)</td>'
            . '<td style="text-align: right">Стр. {PAGENO} из {nbpg}</td></tr></table>';
    }

    /** @param array<string, string> $images имя переменной mpdf `imageVars` по публичному ID ребёнка */
    public function body(SalesReportOutputDto $report, array $images): string
    {
        $html = '<p class="kind">' . $this->escape($this->kind($report->level)) . '</p>'
            . '<h1>' . $this->escape($this->title($report)) . '</h1>'
            . '<p class="area">' . $this->escape($this->area($report)) . '</p>'
            . $this->tiles($report->totals);
        if (SalesReportLevelEnum::GROUP === $report->level) {
            $html .= '<h2>Дети группы</h2>' . $this->children($report, $images);
        } else {
            $html .= '<h2>' . $this->escape($this->rowsTitle($report->level)) . '</h2>' . $this->rows($report);
        }
        if ([] !== $report->products) {
            $html .= '<h2>Печать по форматам</h2><table class="rows"><thead><tr><th>Формат</th><th class="num">Отпечатков</th></tr></thead><tbody>';
            foreach ($report->products as $product) {
                $html .= '<tr><td>' . $this->escape($product->name) . '</td><td class="num">' . $this->number($product->prints) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html . '<p class="note">Проданным считается только подтверждённая оплата. Оплата после закрытия продаж ждёт решения'
            . ' и в суммы не входит. Бесплатные копии печати и подарок не считаются продажей. Данные покупателей в отчёт не включаются.</p>';
    }

    private function kind(SalesReportLevelEnum $level): string
    {
        return match ($level) {
            SalesReportLevelEnum::GROUP => 'Группа',
            SalesReportLevelEnum::SHOOT => 'Съёмка',
            SalesReportLevelEnum::INSTITUTION => 'Учреждение',
            SalesReportLevelEnum::ALL => 'Отчёт о продажах',
        };
    }

    private function title(SalesReportOutputDto $report): string
    {
        return match ($report->level) {
            SalesReportLevelEnum::GROUP => $report->group->name ?? '',
            SalesReportLevelEnum::SHOOT => $report->shoot->name ?? '',
            SalesReportLevelEnum::INSTITUTION => $report->institution->name ?? '',
            SalesReportLevelEnum::ALL => 'Все учреждения',
        };
    }

    private function area(SalesReportOutputDto $report): string
    {
        $parts = [];
        if (null !== $report->institution && SalesReportLevelEnum::INSTITUTION !== $report->level) {
            $parts[] = $report->institution->name;
        }
        if (null !== $report->shoot && SalesReportLevelEnum::GROUP === $report->level) {
            $parts[] = 'съёмка «' . $report->shoot->name . '»';
        }

        return [] === $parts ? 'Продажи фотографий в вашей области' : implode(' · ', $parts);
    }

    private function rowsTitle(SalesReportLevelEnum $level): string
    {
        return match ($level) {
            SalesReportLevelEnum::ALL => 'Учреждения',
            SalesReportLevelEnum::INSTITUTION => 'Съёмки',
            default => 'Группы',
        };
    }

    private function tiles(SalesTotalsOutputDto $totals): string
    {
        $tiles = [
            [$this->money($totals->paidTotal), 'оплачено родителями'],
            [$this->number($totals->paidOrders), $this->plural($totals->paidOrders, 'оплаченный заказ', 'оплаченных заказа', 'оплаченных заказов')],
            [$this->number($totals->orderedFamilies) . ' / ' . $this->number($totals->paidFamilies), 'заказали / оплатили, из ' . $this->number($totals->children) . ' ' . $this->plural($totals->children, 'ребёнка', 'детей', 'детей')],
            [$this->number($totals->prints), $this->plural($totals->prints, 'печатное фото', 'печатных фото', 'печатных фото')],
            [$this->number($totals->digitalFiles), $this->plural($totals->digitalFiles, 'цифровой файл', 'цифровых файла', 'цифровых файлов')
                . (0 < $totals->bundles ? ' и ' . $this->number($totals->bundles) . ' ' . $this->plural($totals->bundles, 'комплект', 'комплекта', 'комплектов') . ' «все фото»' : '')],
        ];
        $html = '<table class="tiles"><tr>';
        foreach ($tiles as [$value, $label]) {
            $html .= '<td class="tile" width="20%"><div class="tile-value">' . $this->escape($value) . '</div><div class="tile-label">' . $this->escape($label) . '</div></td>';
        }

        return $html . '</tr></table>';
    }

    private function rows(SalesReportOutputDto $report): string
    {
        $html = '<table class="rows"><thead><tr><th>Название</th><th class="num">Детей</th><th class="num">Заказали</th><th class="num">Оплатили</th>'
            . '<th class="num">Заказов</th><th class="num">Печать</th><th class="num">Цифровые</th><th class="num">Сумма</th></tr></thead><tbody>';
        if ([] === $report->rows) {
            return $html . '<tr><td colspan="8" class="muted">Продаж пока нет</td></tr></tbody></table>';
        }
        foreach ($report->rows as $row) {
            $html .= $this->row($row->name, $row->totals, '');
        }

        return $html . $this->row('Итого', $report->totals, ' class="total"') . '</tbody></table>';
    }

    private function row(string $name, SalesTotalsOutputDto $totals, string $attributes): string
    {
        return '<tr' . $attributes . '><td>' . $this->escape($name) . '</td>'
            . '<td class="num">' . $this->number($totals->children) . '</td>'
            . '<td class="num">' . $this->number($totals->orderedFamilies) . '</td>'
            . '<td class="num">' . $this->number($totals->paidFamilies) . '</td>'
            . '<td class="num">' . $this->number($totals->paidOrders) . '</td>'
            . '<td class="num">' . $this->number($totals->prints) . '</td>'
            . '<td class="num">' . $this->number($totals->digitalFiles + $totals->bundles) . '</td>'
            . '<td class="num">' . $this->escape($this->money($totals->paidTotal)) . '</td></tr>';
    }

    /** @param array<string, string> $images */
    private function children(SalesReportOutputDto $report, array $images): string
    {
        $html = '<table class="rows"><thead><tr><th></th><th>Ребёнок</th><th>Статус</th><th class="num">Заказов</th>'
            . '<th class="num">Печать</th><th class="num">Цифровые</th><th class="num">Сумма</th></tr></thead><tbody>';
        if ([] === $report->children) {
            return $html . '<tr><td colspan="7" class="muted">В группе пока нет детей</td></tr></tbody></table>';
        }
        foreach ($report->children as $child) {
            // The bytes stay in mpdf imageVars: base64 frames inside the HTML exceed the PCRE limit of mpdf on a real group.
            $image = $images[$child->id] ?? null;
            $html .= '<tr><td width="11mm">' . (null === $image ? '' : '<img src="var:' . $this->escape($image) . '" width="9mm">') . '</td>'
                . '<td class="code">' . $this->escape($child->code) . '</td>'
                . '<td><span class="status status-' . $child->status->value . '">' . $this->escape($this->status($child)) . '</span></td>'
                . '<td class="num">' . $this->number($child->paidOrders) . ($child->orders > $child->paidOrders ? ' <span class="muted">из ' . $this->number($child->orders) . '</span>' : '') . '</td>'
                . '<td class="num">' . $this->number($child->prints) . '</td>'
                . '<td class="num">' . $this->number($child->digitalFiles + $child->bundles) . '</td>'
                . '<td class="num">' . $this->escape($this->money($child->paidTotal)) . '</td></tr>';
        }

        return $html . $this->childTotal($report->totals) . '</tbody></table>';
    }

    private function childTotal(SalesTotalsOutputDto $totals): string
    {
        return '<tr class="total"><td></td><td colspan="2">Итого</td><td class="num">' . $this->number($totals->paidOrders) . '</td>'
            . '<td class="num">' . $this->number($totals->prints) . '</td>'
            . '<td class="num">' . $this->number($totals->digitalFiles + $totals->bundles) . '</td>'
            . '<td class="num">' . $this->escape($this->money($totals->paidTotal)) . '</td></tr>';
    }

    private function status(ChildSalesOutputDto $child): string
    {
        return match ($child->status) {
            ChildSalesStatusEnum::PAID => 'Оплатил',
            ChildSalesStatusEnum::LATE => 'Поздняя оплата',
            ChildSalesStatusEnum::UNPAID => 'Заказ без оплаты',
            ChildSalesStatusEnum::NONE => 'Нет заказа',
        };
    }

    private function money(int $kopecks): string
    {
        $rubles = number_format(intdiv($kopecks, 100), 0, ',', self::NBSP);
        $rest = $kopecks % 100;

        return $rubles . (0 === $rest ? '' : ',' . sprintf('%02d', $rest)) . self::NBSP . '₽';
    }

    private function number(int $value): string
    {
        return number_format($value, 0, ',', self::NBSP);
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $tens = $count % 100;
        $units = $count % 10;

        return match (true) {
            $tens >= 11 && $tens <= 14 => $many,
            1 === $units => $one,
            $units >= 2 && $units <= 4 => $few,
            default => $many,
        };
    }

    private function moment(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
