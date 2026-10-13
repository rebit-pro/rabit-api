<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Morefoto\Media\Application\Gallery\Service\GalleryLinks;
use Morefoto\Media\Application\Gallery\UseCase\GetGalleryUseCase;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Gallery\ValueObject\GalleryAlias;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * F3: the short alias of a gallery key — format, resolution in MED-01 and issue with the key.
 *
 * @internal
 */
final class GalleryAliasTest extends TestCase
{
    public function testAliasIsTenSymbolsOfTheUnambiguousAlphabet(): void
    {
        // F3-T01: 32 symbols without 0/o and 1/l; many draws use the whole alphabet and never repeat.
        $seen = [];
        $symbols = [];
        for ($draw = 0; $draw < 2000; ++$draw) {
            $alias = GalleryAlias::random();
            self::assertMatchesRegularExpression('/^[2-9a-km-np-z]{10}$/D', $alias);
            $seen[$alias] = true;
            $symbols += array_fill_keys(str_split($alias), true);
        }
        self::assertCount(2000, $seen);
        self::assertSame(str_split(GalleryAlias::ALPHABET), array_values(array_intersect(str_split(GalleryAlias::ALPHABET), array_keys($symbols))));
        self::assertSame(32, strlen(GalleryAlias::ALPHABET));
        foreach (['0', 'o', '1', 'l', 'A'] as $symbol) {
            self::assertFalse(GalleryAlias::matches(str_repeat($symbol, 10)), $symbol);
        }
        self::assertFalse(GalleryAlias::matches(str_repeat('a', 64)), 'A long key is not an alias.');
    }

    public function testGalleryOpensByAliasAsTheSameKey(): void
    {
        // F3-T03: the alias resolves to the long key, which is what the storefront, quotes and orders keep using.
        $token = str_repeat('c', 64);
        $keys = $this->createMock(GalleryCapabilityRepository::class);
        $keys->expects(self::once())->method('tokenByAlias')->with('k3m9x2q7v4')->willReturn($token);
        $access = $this->createMock(GalleryAccessInterface::class);
        $access->expects(self::once())->method('resolve')->with($token)->willReturn($this->view());

        self::assertSame($token, new GetGalleryUseCase($access, $keys)->execute('k3m9x2q7v4')->token);
    }

    public function testLongKeyIsUnchangedAndUnknownAliasIsNotFound(): void
    {
        $token = str_repeat('d', 64);
        $keys = $this->createMock(GalleryCapabilityRepository::class);
        $keys->expects(self::once())->method('tokenByAlias')->with('zzzzzzzzzz')->willReturn(null);
        $access = $this->createMock(GalleryAccessInterface::class);
        $access->expects(self::once())->method('resolve')->with($token)->willReturn($this->view());
        $useCase = new GetGalleryUseCase($access, $keys);

        self::assertSame($token, $useCase->execute($token)->token);
        $this->expectExceptionObject(new HttpException('GALLERY_NOT_FOUND', 404));
        $useCase->execute('zzzzzzzzzz');
    }

    public function testPreparationGivesAnAliasOnlyWhereItIsMissingAndKeepsTheKey(): void
    {
        // F3-T02: a key left without an alias gets one; a repeat reads the same link; the key is not reissued.
        $row = ['TOKEN' => str_repeat('e', 64), 'TOKEN_HASH' => str_repeat('f', 64), 'ALIAS' => null, 'CREATED_AT' => '2026-10-12 09:00:00'];
        $keys = $this->createMock(GalleryCapabilityRepository::class);
        $keys->method('current')->willReturnOnConsecutiveCalls($row, ['ALIAS' => 'k3m9x2q7v4'] + $row, ['ALIAS' => 'k3m9x2q7v4'] + $row, ['ALIAS' => 'k3m9x2q7v4'] + $row);
        $keys->expects(self::once())->method('attachAlias')->with(str_repeat('f', 64))->willReturn('k3m9x2q7v4');
        $keys->expects(self::never())->method('issue');
        $links = new GalleryLinks($keys, new GalleryCapabilityLifecycle($keys, $this->createStub(GalleryGroupInterface::class)));

        $first = $links->ensure('group-id');
        $again = $links->ensure('group-id');

        self::assertSame(['k3m9x2q7v4', str_repeat('e', 64), '2026-10-12 09:00:00'], [$first->alias, $first->token, $first->issuedAt->format('Y-m-d H:i:s')]);
        self::assertSame($first->alias, $again->alias);
    }

    private function view(): GalleryAccessOutputDto
    {
        $group = new GalleryGroupOutputDto(1, 2, 'group-id', 'Institution', 'Shoot', 'Group', 'regular', '2026-09-21 00:00:00', '2026-09-28 00:00:00', '2026-10-05 00:00:00', 1);

        return new GalleryAccessOutputDto($group, 'open', '2026-09-21T10:00:00+00:00', 1, []);
    }
}
