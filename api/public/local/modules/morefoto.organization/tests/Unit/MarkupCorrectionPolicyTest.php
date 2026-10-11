<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\Service\MarkupCorrectionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * D4-T01: исправление разметки начинается только у переданной группы, пока она продаёт.
 *
 * @internal
 */
final class MarkupCorrectionPolicyTest extends TestCase
{
    #[DataProvider('selling')]
    public function testSellingGroupMayBeCorrected(string $status): void
    {
        new MarkupCorrectionPolicy()->assertCanStart($status);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function selling(): iterable
    {
        yield 'print and digital' => ['open'];
        yield 'digital only' => ['digital'];
    }

    #[DataProvider('refused')]
    public function testUntransmittedAndClosedGroupsAreRefused(string $status, string $code): void
    {
        try {
            new MarkupCorrectionPolicy()->assertCanStart($status);
            self::fail('Expected ' . $code);
        } catch (CalendarRuleViolation $violation) {
            self::assertSame($code, $violation->getMessage());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function refused(): iterable
    {
        yield 'before transmission the markup is simply edited' => ['preparing', 'LINK_NOT_SENT'];
        yield 'nothing to sell after all sales' => ['closed', 'GALLERY_CLOSED'];
    }
}
