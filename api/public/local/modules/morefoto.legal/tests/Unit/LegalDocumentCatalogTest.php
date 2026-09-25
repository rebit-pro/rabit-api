<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;
use Morefoto\Legal\Application\Document\Service\LegalTextRenderer;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Infrastructure\Document\FileLegalDocumentCatalog;
use Morefoto\Legal\Infrastructure\Seller\ConfiguredSellerProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Проверяет настоящий реестр: каждая редакция имеет файл, а тексты рендерятся без незаполненных подстановок.
 *
 * @internal
 */
final class LegalDocumentCatalogTest extends TestCase
{
    private const string DOCUMENTS = __DIR__ . '/../../documents';

    public function testEveryRegisteredVersionHasARenderableText(): void
    {
        $catalog = new FileLegalDocumentCatalog(self::DOCUMENTS);
        $renderer = new LegalTextRenderer();
        $unpublished = new SellerOutputDto(false, null, null, null, null, null, null);
        foreach (LegalDocumentEnum::cases() as $code) {
            $versions = $catalog->versions($code);
            self::assertEquals(end($versions), $catalog->current($code));
            foreach ($versions as $version) {
                self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/D', $version->effectiveFrom);
                self::assertNotSame('', $version->title);
                $blocks = $renderer->render($catalog->text($version), $unpublished);
                self::assertNotEmpty($blocks, $code->value);
                self::assertStringNotContainsString('{{', (string)json_encode($blocks, JSON_UNESCAPED_UNICODE), $code->value);
            }
        }
    }

    public function testConsentsAreSeparateDocumentsThatRequireReconsent(): void
    {
        $catalog = new FileLegalDocumentCatalog(self::DOCUMENTS);

        self::assertTrue($catalog->current(LegalDocumentEnum::BUYER_CONSENT)->reconsent);
        self::assertTrue($catalog->current(LegalDocumentEnum::STAFF_CONSENT)->reconsent);
        self::assertStringNotContainsString('Согласие покупателя', $catalog->text($catalog->current(LegalDocumentEnum::OFFER)));
    }

    public function testSellerIsPublishedOnlyWithAllRequiredRequisites(): void
    {
        $values = ['name' => 'ИП Тестов Т. Т.', 'inn' => '366200000000', 'ogrnip' => '300000000000000', 'address' => 'Воронеж', 'email' => 'pd@example.test', 'phone' => false];

        self::assertTrue((new ConfiguredSellerProvider($values))->seller()->published);
        self::assertNull((new ConfiguredSellerProvider($values))->seller()->phone);
        self::assertFalse((new ConfiguredSellerProvider(['ogrnip' => '  '] + $values))->seller()->published);
    }
}
