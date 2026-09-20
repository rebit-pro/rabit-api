<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Unit\Delivery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;

/**
 * @internal
 */
final class EmailNotificationInputDtoTest extends TestCase
{
    /**
     * @param array<string, mixed> $replace
     */
    #[DataProvider('invalidInputProvider')]
    public function testRejectsInvalidContractInput(array $replace): void
    {
        $data = [
            'consumer' => 'payments.receipt',
            'deduplicationKey' => 'order-42',
            'recipient' => 'buyer@example.test',
            'subject' => 'Чек',
            'body' => 'Тело',
            'maxAttempts' => 3,
        ];

        $this->expectException(\InvalidArgumentException::class);

        new EmailNotificationInputDto(...array_replace($data, $replace));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'consumer' => [['consumer' => 'Payments Receipt']];
        yield 'dedup key' => [['deduplicationKey' => 'bad key']];
        yield 'recipient' => [['recipient' => 'not-email']];
        yield 'subject newline' => [['subject' => "subject\nBcc: x@example.test"]];
        yield 'empty body' => [['body' => '   ']];
        yield 'attempt limit' => [['maxAttempts' => 11]];
    }
}
