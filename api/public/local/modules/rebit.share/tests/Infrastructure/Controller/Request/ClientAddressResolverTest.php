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

    public function testPublicMappedPeerIsTheClientLikeItsIpv4Form(): void
    {
        self::assertSame('8.8.8.8', $this->resolver->resolve('8.8.8.8', '1.1.1.1'));
        self::assertSame('8.8.8.8', $this->resolver->resolve('::ffff:8.8.8.8', '1.1.1.1'));
        self::assertSame('203.0.113.7', $this->resolver->resolve('::FFFF:203.0.113.7', '198.51.100.1'));
        self::assertSame('203.0.113.7', $this->resolver->resolve('0:0:0:0:0:ffff:cb00:7107', '198.51.100.1'));
    }

    public function testPrivateMappedPeerIsAProxyLikeItsIpv4Form(): void
    {
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '203.0.113.7, 10.0.1.4'));
        self::assertSame('203.0.113.7', $this->resolver->resolve('::ffff:172.18.0.5', '203.0.113.7, 10.0.1.4'));
        self::assertSame('172.18.0.5', $this->resolver->resolve('::ffff:172.18.0.5', null));
    }

    public function testMappedHopsAreJudgedAsIpv4(): void
    {
        // A public mapped hop is the client: the spoofed left element stays unused.
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '198.51.100.1, ::ffff:203.0.113.7, 10.0.1.4'));
        // A private mapped hop is a proxy and is passed like its IPv4 form.
        self::assertSame('203.0.113.7', $this->resolver->resolve('172.18.0.5', '203.0.113.7, ::ffff:10.0.1.4'));
        self::assertSame('172.17.0.1', $this->resolver->resolve('::ffff:172.18.0.5', '::ffff:172.17.0.1, ::ffff:10.0.1.4'));
    }

    public function testPlainIpv6KeepsItsForm(): void
    {
        self::assertSame('2001:db8::ffff:cb00:7107', $this->resolver->resolve('fd00::5', '2001:db8::ffff:203.0.113.7'));
        self::assertSame('::1', $this->resolver->resolve('::1', null));
        self::assertSame('2001:db8::1', $this->resolver->resolve('::1', '2001:db8::1, fd00::5'));
    }

    public function testMissingPeerIsUnknown(): void
    {
        self::assertSame('', $this->resolver->resolve(null, '203.0.113.7'));
        self::assertSame('', $this->resolver->resolve('not-an-ip', null));
    }
}
