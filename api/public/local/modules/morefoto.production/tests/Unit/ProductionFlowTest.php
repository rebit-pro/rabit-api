<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Tests\Unit\Support\ProductionFixture;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * L1-T01…T04, T08…T15: снимок, сборка, дополнение, права и ссылка архива печати на настоящих ZIP и файлах.
 *
 * @internal
 */
final class ProductionFlowTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testRunBuildsOnlyPaidPrintAnHourAfterTheDeadlineAndOnlyOnce(): void
    {
        $f = $this->closedGroup();
        $f->at('2026-10-15 12:59:59');
        self::assertSame(0, $f->run()->scheduled);

        $f->at('2026-10-15 13:00:00');
        $run = $f->run();
        self::assertSame([1, 1, 0], [$run->scheduled, $run->built, $run->failed]);
        self::assertSame(0, $f->run()->scheduled, 'Повторный прогон не создаёт вторую версию 1.');

        $version = array_values($f->archives->versions)[0];
        self::assertSame(VersionStatusEnum::READY, $version->status);
        self::assertSame([3, 2, 5], [$version->linesCount, $version->filesCount, $version->printsCount]);
        self::assertNull($version->createdBy);
        self::assertEquals(new \DateTimeImmutable('2026-10-29 13:00:00', new \DateTimeZone('UTC')), $version->expiresAt);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->storage()->absolutePath($version->archivePath)));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string)$zip->getNameIndex($i);
        }
        sort($names);
        self::assertSame(['Магнит 10×15/B002_x2.jpg', 'Печать 10×15/A001_x3.jpg', 'состав.csv'], $names);
        self::assertSame('JPEG:A001', $zip->getFromName('Печать 10×15/A001_x3.jpg'));
        $csv = (string)$zip->getFromName('состав.csv');
        $zip->close();
        self::assertStringStartsWith("\u{FEFF}\"Продукция\";", $csv);
        self::assertSame(4, substr_count($csv, "\r\n"), 'Заголовок и три строки заказа.');
        self::assertStringNotContainsString('A009', $csv, 'Оплата после срока в печать не входит.');
    }

    public function testGroupWithoutPaidPrintGetsAnEmptyVersionWithoutZip(): void
    {
        $f = new ProductionFixture();
        $f->group(ProductionFixture::GROUP, 10, 7, '2026-10-15 12:00:00');
        $f->at('2026-10-15 13:00:00');
        self::assertSame([1, 0], [$f->run()->scheduled, $f->run()->built]);

        $view = $f->groupView()->execute(ProductionFixture::CURATOR, ProductionFixture::GROUP);
        self::assertSame('empty', $view->state);
        self::assertSame('empty', $view->versions[0]->status);
        self::assertNull($view->versions[0]->archiveUrl);
        self::assertSame([], glob($f->root . '/files/production/*/*.zip'));
    }

    public function testLateConfirmedPaymentGoesIntoASupplementWithOnlyNewLines(): void
    {
        $f = $this->closedGroup();
        $f->at('2026-10-15 13:00:00');
        $f->run();
        $first = array_values($f->archives->versions)[0];

        // The webhook came late, but the provider confirmed the payment before the deadline.
        $f->paidLine(ProductionFixture::GROUP, 'line-005', 'C003', 'print-10x15', 'Печать 10×15', 1, 1, '2026-10-15 11:59:00');
        $f->waiting[ProductionFixture::GROUP] = 1;
        $view = $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP);
        self::assertSame([1, 1, 1, true], [$view->outsideLines, $view->outsidePrints, $view->waitingOrders, $view->canManage]);

        $supplement = $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP, self::KEY);
        self::assertSame([2, 'pending', 1, 1, true], [$supplement->number, $supplement->status, $supplement->lines, $supplement->prints, $supplement->manual]);
        self::assertSame($supplement->id, $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP, self::KEY)->id, 'Тот же Idempotency-Key — та же версия.');
        $this->expectHttp('NOTHING_TO_BUILD', 409, static fn() => $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP, str_repeat('b', 32)));
        $this->expectHttp('IDEMPOTENCY_CONFLICT', 409, static fn() => $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::OTHER_GROUP, self::KEY));

        $f->run();
        self::assertSame(3, $f->archives->find($first->publicId)?->linesCount, 'Собранная версия не меняется.');
        self::assertSame(VersionStatusEnum::READY, $f->archives->find($supplement->id)?->status);
        self::assertSame(0, $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP)->outsideLines);
    }

    public function testGroupClosedBeforeTheReleaseWaitsForTheOrganizer(): void
    {
        $f = new ProductionFixture();
        $f->group(ProductionFixture::GROUP, 10, 7, '2026-10-14 12:00:00');
        $f->paidLine(ProductionFixture::GROUP, 'line-001', 'A001', 'print-10x15', 'Печать 10×15', 1, 1, '2026-10-12 10:00:00');
        $f->at('2026-10-15 13:00:00');
        self::assertSame(0, $f->run()->scheduled);
        self::assertSame('manual', $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP)->state);

        $version = $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP, self::KEY);
        self::assertSame([1, 'pending'], [$version->number, $version->status]);
        $f->run();
        self::assertSame('ready', $f->version()->execute(ProductionFixture::CURATOR, $version->id)->status);
    }

    public function testOpenGroupCannotBeBuilt(): void
    {
        $f = new ProductionFixture();
        $f->group(ProductionFixture::GROUP, 10, 7, '2026-10-20 12:00:00');
        self::assertSame('open', $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP)->state);
        $this->expectHttp('PRINT_SALES_OPEN', 409, static fn() => $f->create()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP, self::KEY));
    }

    public function testRolesSeeOnlyTheirScopeAndOnlyTheOrganizerManages(): void
    {
        $f = $this->closedGroup();
        $f->group(ProductionFixture::OTHER_GROUP, 11, 8, null);
        $f->at('2026-10-15 13:00:00');
        $f->run();
        $version = array_values($f->archives->versions)[0];

        self::assertFalse($f->groupView()->execute(ProductionFixture::CURATOR, ProductionFixture::GROUP)->canManage);
        $this->expectHttp('GROUP_NOT_FOUND', 404, static fn() => $f->groupView()->execute(ProductionFixture::FOREIGN_CURATOR, ProductionFixture::GROUP));
        $this->expectHttp('VERSION_NOT_FOUND', 404, static fn() => $f->version()->execute(ProductionFixture::FOREIGN_CURATOR, $version->publicId));
        $this->expectHttp('VERSION_NOT_FOUND', 404, static fn() => $f->export()->execute(ProductionFixture::FOREIGN_CURATOR, $version->publicId));
        $this->expectHttp('FORBIDDEN', 403, static fn() => $f->groupView()->execute(ProductionFixture::TEACHER, ProductionFixture::GROUP));
        $this->expectHttp('FORBIDDEN', 403, static fn() => $f->create()->execute(ProductionFixture::CURATOR, ProductionFixture::GROUP, self::KEY));
        $this->expectHttp('FORBIDDEN', 403, static fn() => $f->renew()->execute(ProductionFixture::CURATOR, $version->publicId, self::KEY));

        $statuses = $f->list()->execute(ProductionFixture::CURATOR, [ProductionFixture::GROUP, ProductionFixture::OTHER_GROUP, '55555555-5555-4555-8555-555555555555']);
        self::assertSame([[ProductionFixture::GROUP, 'ready', 1]], array_map(static fn($item): array => [$item->groupId, $item->state, $item->version], $statuses));
        self::assertSame(['open', null], [$f->list()->execute(ProductionFixture::ORGANIZER, [ProductionFixture::OTHER_GROUP])[0]->state, $f->list()->execute(ProductionFixture::ORGANIZER, [ProductionFixture::OTHER_GROUP])[0]->version]);
        self::assertStringContainsString('"Печать 10×15/A001_x3.jpg"', $f->export()->execute(ProductionFixture::CURATOR, $version->publicId)->content);
    }

    public function testLinkOpensOnlyItsCurrentGenerationWithinRetention(): void
    {
        $f = $this->closedGroup();
        $f->at('2026-10-15 13:00:00');
        $f->run();
        $view = $f->groupView()->execute(ProductionFixture::CURATOR, ProductionFixture::GROUP)->versions[0];
        self::assertIsString($view->archiveUrl);
        $token = substr($view->archiveUrl, strlen('/api/v1/public/production/archives/'));
        self::assertSame($view->archiveUrl, $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP)->versions[0]->archiveUrl, 'Копирование отдаёт ту же ссылку.');

        $file = $f->open()->execute($token);
        self::assertSame(['/_protected/files/production/' . ProductionFixture::GROUP . '/' . $view->id . '.zip', 'print-11111111-v1.zip', 'application/zip'], [$file->internalUri, $file->filename, $file->mimeType]);
        foreach ([substr($token, 0, -1) . ('A' === $token[42] ? 'B' : 'A'), 'short', str_repeat('A', 43), $f->links->token($view->id, 2)] as $wrong) {
            $this->expectHttp('ARCHIVE_NOT_FOUND', 404, static fn() => $f->open()->execute($wrong));
        }

        $renewed = $f->renew()->execute(ProductionFixture::ORGANIZER, $view->id, self::KEY);
        self::assertNotSame($view->archiveUrl, $renewed->archiveUrl);
        $this->expectHttp('ARCHIVE_NOT_FOUND', 404, static fn() => $f->open()->execute($token));
        self::assertSame($view->id . '.zip', basename($f->open()->execute(substr((string)$renewed->archiveUrl, strlen('/api/v1/public/production/archives/')))->internalUri));
        self::assertSame($renewed->archiveUrl, $f->renew()->execute(ProductionFixture::ORGANIZER, $view->id, self::KEY)->archiveUrl, 'Повтор того же ключа не перевыпускает ссылку ещё раз.');

        $f->at('2026-10-29 13:00:00');
        $newToken = substr((string)$renewed->archiveUrl, strlen('/api/v1/public/production/archives/'));
        $this->expectHttp('ARCHIVE_NOT_FOUND', 404, static fn() => $f->open()->execute($newToken));
        self::assertSame(1, $f->run()->expired);
        self::assertFalse($f->storage()->exists($f->archives->find($view->id)->archivePath ?? ''));
        self::assertSame('expired', $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::GROUP)->state);
        self::assertStringContainsString('A001_x3', $f->export()->execute(ProductionFixture::ORGANIZER, $view->id)->content, 'Состав остаётся после удаления файла.');

        $rebuilt = $f->renew()->execute(ProductionFixture::ORGANIZER, $view->id, str_repeat('c', 32));
        self::assertSame(['pending', null], [$rebuilt->status, $rebuilt->archiveUrl]);
        $f->run();
        $ready = $f->version()->execute(ProductionFixture::ORGANIZER, $view->id);
        self::assertSame('ready', $ready->status);
        self::assertNotSame($renewed->archiveUrl, $ready->archiveUrl);
        $this->expectHttp('VERSION_NOT_RENEWABLE', 409, static function() use ($f): void {
            $f->group(ProductionFixture::OTHER_GROUP, 11, 7, '2026-10-29 10:00:00');
            $f->run();
            $empty = $f->groupView()->execute(ProductionFixture::ORGANIZER, ProductionFixture::OTHER_GROUP)->versions[0];
            $f->renew()->execute(ProductionFixture::ORGANIZER, $empty->id, str_repeat('d', 32));
        });
    }

    public function testMissingOriginalFailsAtOnceAndBrokenWritesRetryThreeTimes(): void
    {
        $f = $this->closedGroup();
        $missing = $f->paid[ProductionFixture::GROUP][2]->photoId;
        unset($f->originalFiles[$missing]);
        $f->at('2026-10-15 13:00:00');
        $f->run();
        $version = array_values($f->archives->versions)[0];
        self::assertSame([VersionStatusEnum::FAILED, 'ORIGINAL_MISSING'], [$version->status, $version->errorCode]);

        $g = $this->closedGroup();
        unlink($g->originalFiles[$g->paid[ProductionFixture::GROUP][0]->photoId]->absolutePath);
        $g->at('2026-10-15 13:00:00');
        foreach (['2026-10-15 13:00:00', '2026-10-15 13:05:00', '2026-10-15 13:10:00'] as $moment) {
            $g->at($moment);
            self::assertSame(1, $g->run()->failed);
        }
        $failed = array_values($g->archives->versions)[0];
        self::assertSame([VersionStatusEnum::FAILED, 'ARCHIVE_FAILED', 3], [$failed->status, $failed->errorCode, $failed->attempts]);
        self::assertSame([], glob($g->root . '/files/production/*/*'), 'Временный файл неудачной сборки не остаётся.');
    }

    /** Группа закрыта 15.10 в 12:00 UTC: две строки одного кадра в одной продукции, комплект магнитов, оплата после срока. */
    private function closedGroup(): ProductionFixture
    {
        $f = new ProductionFixture();
        $f->group(ProductionFixture::GROUP, 10, 7, '2026-10-15 12:00:00');
        $f->paidLine(ProductionFixture::GROUP, 'line-001', 'A001', 'print-10x15', 'Печать 10×15', 2);
        $f->paidLine(ProductionFixture::GROUP, 'line-002', 'A001', 'print-10x15', 'Печать 10×15', 1, 1, '2026-10-11 09:00:00', 'staff');
        $f->paidLine(ProductionFixture::GROUP, 'line-003', 'B002', 'magnet', 'Магнит 10×15', 1, 2);
        $f->paidLine(ProductionFixture::GROUP, 'line-004', 'A009', 'print-10x15', 'Печать 10×15', 1, 1, '2026-10-15 12:30:00');

        return $f;
    }

    private function expectHttp(string $code, int $status, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame([$code, $status], [$error->getMessage(), $error->getCode()]);
        }
    }
}
