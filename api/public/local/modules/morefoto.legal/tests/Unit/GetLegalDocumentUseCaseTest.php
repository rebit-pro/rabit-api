<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use Morefoto\Legal\Application\Document\Contract\SellerProviderInterface;
use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Application\Document\Service\LegalTextRenderer;
use Morefoto\Legal\Application\Document\UseCase\GetLegalDocumentUseCase;
use Morefoto\Legal\Application\Document\UseCase\ListLegalDocumentsUseCase;
use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GetLegalDocumentUseCaseTest extends TestCase
{
    public function testCurrentAndArchivedVersionsAreServedNewestFirst(): void
    {
        $useCase = $this->useCase();

        $current = $useCase->execute('privacy', null);
        self::assertTrue($current->current);
        self::assertSame('2026-10-01', $current->document->version);
        self::assertSame(['2026-10-01', '2026-09-25'], array_map(static fn(object $version): string => $version->version, $current->versions));
        self::assertSame('Текст 2026-10-01', $current->blocks[0]->text);

        $archived = $useCase->execute('privacy', '2026-09-25');
        self::assertFalse($archived->current);
        self::assertSame('Текст 2026-09-25', $archived->blocks[0]->text);
    }

    public function testUnknownDocumentOrVersionIsNotFound(): void
    {
        foreach ([['passport', null], ['privacy', '2020-01-01']] as [$code, $version]) {
            try {
                $this->useCase()->execute($code, $version);
                self::fail('DOCUMENT_NOT_FOUND expected.');
            } catch (HttpException $error) {
                self::assertSame('DOCUMENT_NOT_FOUND', $error->getMessage());
                self::assertSame(404, $error->getCode());
            }
        }
    }

    public function testCatalogListsEveryDocumentWithSeller(): void
    {
        $catalog = (new ListLegalDocumentsUseCase($this->catalog(), $this->seller(), new LegalDocumentMapper()))->execute();

        self::assertSame(['privacy', 'offer', 'buyer-consent', 'staff-consent'], array_map(static fn(object $document): string => $document->code, $catalog->documents));
        self::assertFalse($catalog->seller->published);
    }

    private function useCase(): GetLegalDocumentUseCase
    {
        return new GetLegalDocumentUseCase($this->catalog(), $this->seller(), new LegalTextRenderer(), new LegalDocumentMapper());
    }

    private function catalog(): LegalDocumentCatalogInterface
    {
        $versions = static fn(LegalDocumentEnum $code): array => [
            new LegalDocumentVersion($code, '2026-09-25', 'Документ', '2026-09-25', false),
            new LegalDocumentVersion($code, '2026-10-01', 'Документ', '2026-10-01', false),
        ];
        $catalog = $this->createStub(LegalDocumentCatalogInterface::class);
        $catalog->method('versions')->willReturnCallback($versions);
        $catalog->method('current')->willReturnCallback(static fn(LegalDocumentEnum $code): LegalDocumentVersion => $versions($code)[1]);
        $catalog->method('text')->willReturnCallback(static fn(LegalDocumentVersion $version): string => 'Текст ' . $version->version);

        return $catalog;
    }

    private function seller(): SellerProviderInterface
    {
        $seller = $this->createStub(SellerProviderInterface::class);
        $seller->method('seller')->willReturn(new SellerOutputDto(false, null, null, null, null, null, null));

        return $seller;
    }
}
