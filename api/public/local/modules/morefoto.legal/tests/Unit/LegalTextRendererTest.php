<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;
use Morefoto\Legal\Application\Document\Service\LegalTextRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class LegalTextRendererTest extends TestCase
{
    public function testHeadingsParagraphsAndListsBecomeBlocks(): void
    {
        $blocks = (new LegalTextRenderer())->render(<<<'MD'
# Title from the registry

## 1. Section
First line
continues the paragraph.

- item one
  continues the item
- item two
After the list.

### 1.1. Subsection
MD, $this->seller(true));

        self::assertSame(['heading', 'paragraph', 'list', 'paragraph', 'heading'], array_map(static fn(object $block): string => $block->type, $blocks));
        self::assertSame(2, $blocks[0]->level);
        self::assertSame('1. Section', $blocks[0]->text);
        self::assertSame('First line continues the paragraph.', $blocks[1]->text);
        self::assertSame(['item one continues the item', 'item two'], $blocks[2]->items);
        self::assertSame('After the list.', $blocks[3]->text);
        self::assertSame(3, $blocks[4]->level);
    }

    public function testSellerRequisitesAreSubstitutedAndMissingOnesAreAnnounced(): void
    {
        $renderer = new LegalTextRenderer();
        $text = 'Оператор — {{seller.name}}, ИНН {{ seller.inn }}, телефон {{seller.phone}}.';

        self::assertSame('Оператор — ИП Тестов Т. Т., ИНН 366200000000, телефон ' . LegalTextRenderer::MISSING . '.', $renderer->render($text, $this->seller(true))[0]->text);
        self::assertSame('Оператор — ' . LegalTextRenderer::MISSING . ', ИНН ' . LegalTextRenderer::MISSING . ', телефон ' . LegalTextRenderer::MISSING . '.', $renderer->render($text, $this->seller(false))[0]->text);
    }

    public function testUnknownPlaceholderIsAnError(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        (new LegalTextRenderer())->render('{{seller.passport}}', $this->seller(true));
    }

    private function seller(bool $published): SellerOutputDto
    {
        return $published
            ? new SellerOutputDto(true, 'ИП Тестов Т. Т.', '366200000000', '300000000000000', 'Воронеж', 'pd@example.test', null)
            : new SellerOutputDto(false, null, null, null, null, null, null);
    }
}
