<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Tests\Unit\Support\FilesFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J1-T03/T04: право на файлы по оплате, сроку D10 и составу; J1-T06: лимиты архива.
 *
 * @internal
 */
final class FileAccessTest extends TestCase
{
    #[DataProvider('states')]
    public function testStateFollowsPaymentLatenessAndDeadline(string $status, bool $late, ?string $now, FilesStateEnum $expected): void
    {
        $fixture = new FilesFixture()->withOriginals(FilesFixture::P1);
        $fixture->paymentStatus = $status;
        $fixture->latePayment = $late;
        $fixture->now = $now;

        $access = $fixture->access()->byKey(FilesFixture::KEY);

        self::assertSame($expected, $access->state);
        self::assertSame(FilesStateEnum::AVAILABLE === $expected ? [FilesFixture::P1] : [], array_keys($access->files));
    }

    public static function states(): iterable
    {
        yield 'unpaid' => ['unpaid', false, null, FilesStateEnum::UNPAID];
        yield 'pending' => ['pending', false, null, FilesStateEnum::UNPAID];
        yield 'late payment waits for a decision' => ['paid', true, null, FilesStateEnum::REVIEW];
        yield 'paid' => ['paid', false, null, FilesStateEnum::AVAILABLE];
        yield 'a second before the deadline' => ['paid', false, '2027-02-28 06:59:59', FilesStateEnum::AVAILABLE];
        yield 'exactly at the deadline' => ['paid', false, '2027-02-28 07:00:00', FilesStateEnum::EXPIRED];
    }

    public function testBundleExpandsToCurrentPhotosWithoutDuplicatesOrMissingOriginals(): void
    {
        $access = new FilesFixture()->withOriginals(FilesFixture::P1, FilesFixture::P2)->access()->byKey(FilesFixture::KEY);

        self::assertSame(FilesStateEnum::AVAILABLE, $access->state);
        self::assertSame([FilesFixture::P1, FilesFixture::P2], array_keys($access->files));
        // The digital line keeps its purchase-time code; the bundle repeat of P1 does not produce a second file.
        self::assertSame(['AB001.jpg', 'CD001.jpg'], [$access->files[FilesFixture::P1]->filename, $access->files[FilesFixture::P2]->filename]);
    }

    public function testPaidOrderWithoutStoredOriginalsIsEmpty(): void
    {
        self::assertSame(FilesStateEnum::EMPTY, new FilesFixture()->access()->byKey(FilesFixture::KEY)->state);
    }

    public function testForeignKeyIsNotFound(): void
    {
        $this->expectExceptionObject(new HttpException('ORDER_NOT_FOUND', 404));

        new FilesFixture()->access()->byKey(str_repeat('b', 64));
    }

    public function testArchiveLimitsAndLifetimes(): void
    {
        $policy = new FileAccessPolicy();
        $policy->assertArchiveFits(500, 2 * 1024 ** 3);
        foreach ([[501, 1], [1, 2 * 1024 ** 3 + 1]] as [$files, $bytes]) {
            try {
                $policy->assertArchiveFits($files, $bytes);
                self::fail('Expected ARCHIVE_TOO_LARGE');
            } catch (HttpException $error) {
                self::assertSame(['ARCHIVE_TOO_LARGE', 413], [$error->getMessage(), $error->getCode()]);
            }
        }
        $now = new \DateTimeImmutable('2027-02-28 00:00:00');
        $until = new \DateTimeImmutable('2027-02-28 07:00:00');
        self::assertEquals($until, $policy->downloadExpiresAt($now, $until));
        self::assertEquals($now->modify('+10 minutes'), $policy->linkExpiresAt($now, $until));
        self::assertEquals($now->modify('+24 hours'), $policy->downloadExpiresAt($now, $now->modify('+2 days')));
    }
}
