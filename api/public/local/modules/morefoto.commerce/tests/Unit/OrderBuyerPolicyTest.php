<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Order\Service\BuyerPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E8-T01: email — единственный обязательный контакт покупателя; имени и телефона в заказе нет.
 *
 * @internal
 */
final class OrderBuyerPolicyTest extends TestCase
{
    public function testEmailIsTheOnlyContactAndIsNormalized(): void
    {
        $buyer = (new BuyerPolicy())->accept('  Parent@Example.RU ', '  Забрать в саду  ', null, true, []);

        self::assertSame(['parent@example.ru', 'Забрать в саду', null], [$buyer->email, $buyer->comment, $buyer->receiptChannel]);
        self::assertSame(['email', 'comment', 'receiptChannel'], array_keys(get_object_vars($buyer)), 'No name or phone in the order buyer.');
    }

    /** @param array{string, string, ?string, bool} $input */
    #[DataProvider('invalidFields')]
    public function testRejectsEachInvalidFieldWithItsOwnCode(array $input, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);

        (new BuyerPolicy())->accept(...$input, receiptChannels: []);
    }

    /** @return iterable<string, array{array{string, string, ?string, bool}, string}> */
    public static function invalidFields(): iterable
    {
        yield 'empty email' => [['   ', '', null, true], 'INVALID_BUYER_EMAIL'];
        yield 'email without domain' => [['buyer@', '', null, true], 'INVALID_BUYER_EMAIL'];
        yield 'too long email' => [[str_repeat('a', 250) . '@b.ru', '', null, true], 'INVALID_BUYER_EMAIL'];
        yield 'long comment' => [['a@b.ru', str_repeat('к', 1001), null, true], 'INVALID_BUYER_COMMENT'];
        yield 'receipt channel while receipts are off' => [['a@b.ru', '', 'email', true], 'RECEIPT_CHANNEL_UNAVAILABLE'];
        yield 'composition not reviewed' => [['a@b.ru', '', null, false], 'REVIEW_REQUIRED'];
    }

    public function testAcceptsOnlyConnectedReceiptChannel(): void
    {
        self::assertSame('email', (new BuyerPolicy())->accept('a@b.ru', '', 'email', true, ['email'])->receiptChannel);
    }

    public function testConnectedReceiptsGoToTheOrderEmailWithoutAChoice(): void
    {
        self::assertSame('email', (new BuyerPolicy())->accept('a@b.ru', '', null, true, ['email'])->receiptChannel);
        self::assertNull((new BuyerPolicy())->accept('a@b.ru', '', null, true, [])->receiptChannel);
    }
}
