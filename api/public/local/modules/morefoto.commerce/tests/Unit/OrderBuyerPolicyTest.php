<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Order\Service\BuyerPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderBuyerPolicyTest extends TestCase
{
    public function testNormalizesContactsWithoutChangingMeaning(): void
    {
        $buyer = (new BuyerPolicy())->accept('  Анна  ', '8 (900) 123-45-67', ' Buyer@Example.TEST ', "  Комментарий \n", null, true, []);

        self::assertSame('Анна', $buyer->name);
        self::assertSame('+79001234567', $buyer->phone);
        self::assertSame('buyer@example.test', $buyer->email);
        self::assertSame('Комментарий', $buyer->comment);
        self::assertNull($buyer->receiptChannel);
    }

    /** @param array{0: string, 1: string, 2: string, 3: string, 4: ?string, 5: bool} $input */
    #[DataProvider('invalid')]
    public function testRejectsEachInvalidFieldWithItsOwnCode(array $input, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        (new BuyerPolicy())->accept(...[...$input, []]);
    }

    public static function invalid(): iterable
    {
        yield 'short name' => [['А', '+79001234567', 'a@b.ru', '', null, true], 'INVALID_BUYER_NAME'];
        yield 'long name' => [[str_repeat('я', 101), '+79001234567', 'a@b.ru', '', null, true], 'INVALID_BUYER_NAME'];
        yield 'letters in phone' => [['Анна', '+7900abc4567', 'a@b.ru', '', null, true], 'INVALID_BUYER_PHONE'];
        yield 'short phone' => [['Анна', '123456789', 'a@b.ru', '', null, true], 'INVALID_BUYER_PHONE'];
        yield 'email without domain' => [['Анна', '+79001234567', 'buyer@', '', null, true], 'INVALID_BUYER_EMAIL'];
        yield 'long comment' => [['Анна', '+79001234567', 'a@b.ru', str_repeat('к', 1001), null, true], 'INVALID_BUYER_COMMENT'];
        yield 'receipt channel before G2' => [['Анна', '+79001234567', 'a@b.ru', '', 'email', true], 'RECEIPT_CHANNEL_UNAVAILABLE'];
        yield 'composition not reviewed' => [['Анна', '+79001234567', 'a@b.ru', '', null, false], 'REVIEW_REQUIRED'];
    }

    public function testAcceptsOnlyConnectedReceiptChannel(): void
    {
        self::assertSame('email', (new BuyerPolicy())->accept('Анна', '+79001234567', 'a@b.ru', '', 'email', true, ['email'])->receiptChannel);
    }
}
