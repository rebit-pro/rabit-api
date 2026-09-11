<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Infrastructure\Adapter;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Infrastructure\Adapter\TokenGenerator;

/**
 * @internal
 */
final class TokenGeneratorTest extends TestCase
{
    private TokenGenerator $tokenGenerator;

    protected function setUp(): void
    {
        $this->tokenGenerator = new TokenGenerator();
    }

    public function testGeneratesHexStringOf64Characters(): void
    {
        $token = $this->tokenGenerator->generate();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGeneratesUniqueTokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; ++$i) {
            $tokens[] = $this->tokenGenerator->generate();
        }

        self::assertCount(100, array_unique($tokens));
    }
}
