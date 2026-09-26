<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller\Request;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\ClientAddressResolver;

/**
 * @internal
 */
final class ClientAddressResolverTest extends TestCase
{
    private ClientAddressResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ClientAddressResolver();
    }

    public function testPublicPeerIsTheClientAndItsForwardedHeaderIsIgnored(): void
    {
        self::assertSame('203.0.113.7', $this->resolver->resolve('203.0.113.7', '198.51.100.1'));
    }

    public function testClientIsTheRightmostPublicAddressBehindPrivateProxies(): void
    {
        // Traefik puts the client, nginx frontend appends Traefik; fpm sees nginx backend.
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '203.0.113.7, 10.0.1.4'));
    }

    public function testSpoofedLeftElementIsNotTrusted(): void
    {
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '198.51.100.1, 203.0.113.7, 10.0.1.4'));
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '198.51.100.1,203.0.113.7'));
    }

    public function testGarbageStopsAtTheLastCheckedHop(): void
    {
        self::assertSame('10.0.1.4', $this->resolver->resolve('172.18.0.5', '198.51.100.1, unknown, 10.0.1.4'));
        self::assertSame('172.18.0.5', $this->resolver->resolve('172.18.0.5', '203.0.113.7:443'));
    }

    public function testOnlyPrivateHopsGiveTheLeftmostOne(): void
    {
        self::assertSame('172.17.0.1', $this->resolver->resolve('172.18.0.5', '172.17.0.1, 10.0.1.4'));
        self::assertSame('172.18.0.5', $this->resolver->resolve('172.18.0.5', null));
        self::assertSame('127.0.0.1', $this->resolver->resolve('127.0.0.1', ''));
    }

    public function testIpv6IsNormalized(): void
    {
        self::assertSame('2001:db8::1', $this->resolver->resolve('fd00::5', '2001:0DB8:0:0::1'));
        self::assertSame('2001:db8::1', $this->resolver->resolve('2001:db8:0::1', null));
    }

    public function testMissingPeerIsUnknown(): void
    {
        self::assertSame('', $this->resolver->resolve(null, '203.0.113.7'));
        self::assertSame('', $this->resolver->resolve('not-an-ip', null));
    }
}
