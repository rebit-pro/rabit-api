<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GalleryAvailabilityTest extends TestCase
{
    public function testOnlyActualTransmissionOpensTheGalleryAndClosingBoundaryIsExclusive(): void
    {
        $availability = new GalleryAvailability();
        $now = new \DateTimeImmutable('2026-09-21T10:00:00Z');
        self::assertSame('preparing', $availability->state(null, null, $now));
        self::assertSame('preparing', $availability->state('2026-09-21 10:01:00', '2026-09-28 10:01:00', $now));
        self::assertSame('open', $availability->state('2026-09-14 10:00:01', '2026-09-21 10:00:01', $now));
        self::assertSame('closed', $availability->state('2026-09-14 10:00:00', '2026-09-21 10:00:00', $now));
        self::assertSame('closed', $availability->state('2026-09-14 09:59:59', '2026-09-21 09:59:59', $now));
    }
}
