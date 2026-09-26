<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Infrastructure\Crypto\GuestAddressHasher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GuestAddressHasherTest extends TestCase
{
    private const string SECRET = 'unit-test-secret-0123456789abcdef0123';

    public function testHashIsKeyedAndHidesTheAddress(): void
    {
        $hasher = new GuestAddressHasher(self::SECRET);
        $hash = $hasher->hash('203.0.113.7');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $hash);
        self::assertSame($hash, $hasher->hash('203.0.113.7'));
        self::assertNotSame($hash, $hasher->hash('203.0.113.8'));
        self::assertNotSame($hash, (new GuestAddressHasher(strrev(self::SECRET)))->hash('203.0.113.7'));
        self::assertNotSame(hash('sha256', '203.0.113.7'), $hash);
        self::assertNotSame(hash('sha256', (string)inet_pton('203.0.113.7')), $hash);
    }

    public function testIpv6SubscriberIsItsPrefixAndMappedIpv4IsIpv4(): void
    {
        $hasher = new GuestAddressHasher(self::SECRET);

        self::assertSame($hasher->hash('2001:db8:1:2::1'), $hasher->hash('2001:db8:1:2:ffff:ffff:ffff:ffff'));
        self::assertNotSame($hasher->hash('2001:db8:1:2::1'), $hasher->hash('2001:db8:1:3::1'));
        self::assertSame($hasher->hash('203.0.113.7'), $hasher->hash('::ffff:203.0.113.7'));
    }

    public function testMissingSecretOrAddressIsRefused(): void
    {
        foreach ([['', '203.0.113.7'], ['short', '203.0.113.7'], [self::SECRET, ''], [self::SECRET, 'unknown']] as [$secret, $address]) {
            try {
                (new GuestAddressHasher($secret))->hash($address);
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
                continue;
            }
            self::fail('Hashed without a secret or an address: ' . $address);
        }
    }
}
