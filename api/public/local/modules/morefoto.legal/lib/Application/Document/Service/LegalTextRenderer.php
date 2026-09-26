<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Service;

use Morefoto\Legal\Application\Document\Dto\DocumentBlockOutputDto;
use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;

/** Превращает исходный текст юридического документа в блоки для показа без HTML на клиенте.
 * Подставляет реквизиты продавца из окружения и понимает только заголовки, абзацы и списки: этого хватает для
 * документов, а клиенту не нужен разбор Markdown и вывод чужого HTML.
 */
final readonly class LegalTextRenderer
{
    public const string MISSING = '(будет указано до начала продаж)';

    /** @return list<DocumentBlockOutputDto> */
    public function render(string $source, SellerOutputDto $seller): array
    {
        $text = (string)preg_replace_callback(
            '/\{\{\s*([a-z.]+)\s*\}\}/',
            fn(array $match): string => $this->placeholder($match[1], $seller),
            $source,
        );
        $blocks = [];
        $paragraph = [];
        $items = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $trimmed = trim($line);
            if ('' === $trimmed) {
                $this->flush($blocks, $paragraph, $items);
                continue;
            }
            if (1 === preg_match('/^(#{1,3})\s+(.+)$/u', $trimmed, $heading)) {
                $this->flush($blocks, $paragraph, $items);
                // The title of the document comes from the registry, a first-level heading only helps to read the file.
                if (1 < strlen($heading[1])) {
                    $blocks[] = new DocumentBlockOutputDto(type: 'heading', text: $heading[2], level: strlen($heading[1]));
                }
                continue;
            }
            if (str_starts_with($trimmed, '- ')) {
                $this->paragraph($blocks, $paragraph);
                $items[] = trim(substr($trimmed, 2));
                continue;
            }
            if ([] !== $items && str_starts_with($line, '  ')) {
                $items[array_key_last($items)] .= ' ' . $trimmed;
                continue;
            }
            $this->items($blocks, $items);
            $paragraph[] = $trimmed;
        }
        $this->flush($blocks, $paragraph, $items);

        return $blocks;
    }

    /**
     * @param list<DocumentBlockOutputDto> $blocks
     * @param list<string>                 $paragraph
     * @param list<string>                 $items
     */
    private function flush(array &$blocks, array &$paragraph, array &$items): void
    {
        $this->paragraph($blocks, $paragraph);
        $this->items($blocks, $items);
    }

    /**
     * @param list<DocumentBlockOutputDto> $blocks
     * @param list<string>                 $paragraph
     */
    private function paragraph(array &$blocks, array &$paragraph): void
    {
        if ([] !== $paragraph) {
            $blocks[] = new DocumentBlockOutputDto(type: 'paragraph', text: implode(' ', $paragraph));
            $paragraph = [];
        }
    }

    /**
     * @param list<DocumentBlockOutputDto> $blocks
     * @param list<string>                 $items
     */
    private function items(array &$blocks, array &$items): void
    {
        if ([] !== $items) {
            $blocks[] = new DocumentBlockOutputDto(type: 'list', items: $items);
            $items = [];
        }
    }

    private function placeholder(string $name, SellerOutputDto $seller): string
    {
        $value = match ($name) {
            'seller.name' => $seller->name,
            'seller.inn' => $seller->inn,
            'seller.ogrnip' => $seller->ogrnip,
            'seller.address' => $seller->address,
            'seller.email' => $seller->email,
            'seller.phone' => $seller->phone,
            default => throw new \UnexpectedValueException('Unknown legal text placeholder: ' . $name),
        };

        return null === $value ? self::MISSING : $value;
    }
}
