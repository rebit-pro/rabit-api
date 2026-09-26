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

    #[DataProvider('phones')]
    public function testKeepsExplicitInternationalPrefixAndNormalizesOnlyRussianNumbers(string $phone, string $stored): void
    {
        self::assertSame($stored, (new BuyerPolicy())->accept('Анна', $phone, 'a@b.ru', '', null, true, [])->phone);
    }

    public static function phones(): iterable
    {
        yield 'Hong Kong with plus' => ['+85291234567', '+85291234567'];
        yield 'Hong Kong with spaces' => ['+852 9123 4567', '+85291234567'];
        yield 'Hong Kong in brackets' => ['(+852) 9123-4567', '+85291234567'];
        yield 'plus eight is international' => ['+8 900 123-45-67', '+89001234567'];
        yield 'Germany' => ['+49 30 1234567', '+49301234567'];
        yield 'Russian national eight' => ['89001234567', '+79001234567'];
        yield 'Russian national eight formatted' => ['8 (900) 123-45-67', '+79001234567'];
        yield 'Russian plus seven' => ['+7 900 123-45-67', '+79001234567'];
        yield 'Russian seven without plus' => ['79001234567', '+79001234567'];
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
        yield 'short international phone' => [['Анна', '+852 9123 45', 'a@b.ru', '', null, true], 'INVALID_BUYER_PHONE'];
        yield 'long phone' => [['Анна', '+8529123456789012', 'a@b.ru', '', null, true], 'INVALID_BUYER_PHONE'];
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
