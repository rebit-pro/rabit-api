<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Morefoto\Access\Application\Profile\Dto\SupportContactOutputDto;
use Morefoto\Access\Infrastructure\Profile\ConfiguredSupportContactProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SupportContactTest extends TestCase
{
    public function testConfiguredValuesAreTrimmedAndEmptyOnesAreLeftOut(): void
    {
        self::assertEquals(
            new SupportContactOutputDto(name: 'Анна Смирнова', email: 'help@example.invalid', phone: null),
            (new ConfiguredSupportContactProvider('  Анна Смирнова ', 'help@example.invalid', ' '))->contact(),
        );
    }

    public function testNoConfiguredValueMeansNoContact(): void
    {
        self::assertNull((new ConfiguredSupportContactProvider('', " \t", ''))->contact());
    }
}
