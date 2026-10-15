<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Infrastructure\File\CsvManifestWriter;
use Morefoto\Production\Infrastructure\File\LocalArchiveStorage;
use Morefoto\Production\Infrastructure\Security\HmacArchiveLink;
use Morefoto\Production\Presentation\Archive\ProductionInputMapper;
use Morefoto\Production\Presentation\Archive\Request\Dto\CreateVersionRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\ProductionGroupsRequestDto;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * L1-T05, T07 и правила: папки и имена архива, CSV, сроки, состояния, ключ ссылки и разбор запроса.
 *
 * @internal
 */
final class ProductionRulesTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    public function testLayoutSeparatesProductsByIdAndSanitizesFolders(): void
    {
        $entries = array_map(static fn(ArchiveLine $line): string => $line->entry, new ArchiveLayout()->arrange([
            $this->line('l1', 'p-1', 'Печать 10×15', 'A001', 2),
            $this->line('l2', 'p-2', 'Печать 10×15', 'A001', 1),
            $this->line('l3', 'p-1', 'Печать 10×15', 'A001', 1),
            $this->line('l4', 'p-3', '../Магнит: "кот" <10/15>.. ', 'B002', 1),
            $this->line('l5', 'p-4', " \t.. ", 'B../002', 3),
            $this->line('l6', 'p-5', 'печать 10×15', 'C003', 1),
        ]));

        self::assertSame([
            'Печать 10×15/A001_x3',
            'Печать 10×15 (p-2)/A001_x1',
            'Печать 10×15/A001_x3',
            'Магнит кот 10 15/B002_x1',
            'Продукция/B_002_x3',
            'печать 10×15 (p-5)/C003_x1',
        ], $entries);
        foreach ($entries as $entry) {
            self::assertStringNotContainsString('..', $entry);
        }
    }

    public function testManifestIsExcelFriendlyAndDefusesFormulas(): void
    {
        $line = $this->line('l1', 'p-1', '=HYPERLINK("x")', 'A001', 2, 'Печать/A001_x2');
        $csv = new CsvManifestWriter()->csv([$line], ['aaaaaaaa-aaaa-4aaa-8aaa-000000000001' => 'png']);

        self::assertStringStartsWith("\u{FEFF}", $csv);
        self::assertStringEndsWith("\r\n", $csv);
        self::assertStringContainsString('"\'=HYPERLINK(""x"")";"10×15";"Печать/A001_x2.png";"A001";"A";"MF-1";"10.10.2026 13:00";"2";"1";"2";"нет"', $csv);
    }

    public function testPolicyKeepsTheAgreedDelaysAndRetention(): void
    {
        $now = new \DateTimeImmutable('2026-10-15 12:00:00', new \DateTimeZone('UTC'));
        $policy = new ArchivePolicy();

        self::assertSame('2026-10-15 11:00:00', $policy->cutoff($now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-15 13:00:00', $policy->buildFrom($now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-29 12:00:00', $policy->expiresAt($now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-15 12:05:00', $policy->retryAt($now)->format('Y-m-d H:i:s'));
        self::assertSame(3, ArchivePolicy::MAX_ATTEMPTS);
    }

    public function testStateFollowsTheCalendarUntilAVersionExists(): void
    {
        $states = new ProductionStatePolicy();
        $since = new \DateTimeImmutable('2026-10-15 09:00:00');
        $now = new \DateTimeImmutable('2026-10-16 09:00:00');

        self::assertSame('open', $states->state(null, null, $since, $now)->value);
        self::assertSame('open', $states->state(new \DateTimeImmutable('2026-10-17'), null, $since, $now)->value);
        self::assertSame('scheduled', $states->state(new \DateTimeImmutable('2026-10-16 08:30:00'), null, $since, $now)->value);
        self::assertSame('manual', $states->state(new \DateTimeImmutable('2026-10-15 09:00:00'), null, $since, $now)->value);
        foreach (['pending' => 'building', 'ready' => 'ready', 'failed' => 'failed', 'empty' => 'empty', 'expired' => 'expired'] as $status => $state) {
            self::assertSame($state, $states->state(new \DateTimeImmutable('2026-10-15'), $this->version(VersionStatusEnum::from($status)), $since, $now)->value);
        }
    }

    public function testLinkTokenIsStableUnguessableAndNeedsTheSecret(): void
    {
        $links = new HmacArchiveLink(str_repeat('s', 32));
        $token = $links->token(self::ID, 1);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token);
        self::assertSame($token, $links->token(self::ID, 1));
        self::assertNotSame($token, $links->token(self::ID, 2));
        self::assertNotSame($token, new HmacArchiveLink(str_repeat('t', 32))->token(self::ID, 1));
        self::assertSame(hash('sha256', $token), $links->hash($token));
        $this->expectExceptionMessage('REBIT_ENCRYPTION_KEY');
        new HmacArchiveLink('short')->token(self::ID, 1);
    }

    public function testStorageAcceptsOnlyItsOwnArchivePaths(): void
    {
        $storage = new LocalArchiveStorage('/srv/files/');
        $path = $storage->archivePath(self::ID, self::ID);

        self::assertSame('production/' . self::ID . '/' . self::ID . '.zip', $path);
        self::assertSame('/srv/files/' . $path, $storage->absolutePath($path));
        self::assertSame('/_protected/files/' . $path, $storage->uri($path));
        foreach (['archives/a/b.zip', 'production/../x.zip', '/etc/passwd', ''] as $unsafe) {
            try {
                $storage->uri($unsafe);
                self::fail('Accepted unsafe path ' . $unsafe);
            } catch (ProductionStorageException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(ProductionStorageException::class);
        $storage->archivePath('../x', self::ID);
    }

    public function testInputMapperValidatesGroupsAndIdempotencyKeys(): void
    {
        $mapper = new ProductionInputMapper();

        self::assertSame([self::ID], $mapper->groupIds(new ProductionGroupsRequestDto(self::ID . ',' . self::ID)));
        self::assertSame(str_repeat('ab', 16), $mapper->key(new CreateVersionRequestDto(self::ID, str_repeat('AB', 16))));
        foreach ([
            ['INVALID_GROUPS', static fn() => $mapper->groupIds(new ProductionGroupsRequestDto())],
            ['INVALID_GROUPS', static fn() => $mapper->groupIds(new ProductionGroupsRequestDto('x'))],
            ['INVALID_GROUPS', static fn() => $mapper->groupIds(new ProductionGroupsRequestDto(implode(',', array_map(static fn(int $i): string => sprintf('11111111-1111-4111-8111-%012d', $i), range(1, 101)))))],
            ['INVALID_IDEMPOTENCY_KEY', static fn() => $mapper->key(new CreateVersionRequestDto(self::ID, 'short'))],
        ] as [$code, $call]) {
            try {
                $call();
                self::fail('Expected ' . $code);
            } catch (HttpException $error) {
                self::assertSame([$code, 422], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    private function line(string $id, string $productId, string $productName, string $photoCode, int $prints, string $entry = ''): ArchiveLine
    {
        return new ArchiveLine(
            $id,
            'order',
            'MF-1',
            '2026-10-10 10:00:00',
            'regular',
            'A',
            'aaaaaaaa-aaaa-4aaa-8aaa-000000000001',
            $photoCode,
            $productId,
            $productName,
            '10×15',
            1,
            $prints,
            $prints,
            $entry,
        );
    }

    private function version(VersionStatusEnum $status): ArchiveVersion
    {
        $now = new \DateTimeImmutable('2026-10-15');

        return new ArchiveVersion(1, self::ID, 10, self::ID, 7, $now, 1, $status, 1, 1, 1, 0, 'production/x.zip', null, null, 0, 1, $now, null, null, null);
    }
}
