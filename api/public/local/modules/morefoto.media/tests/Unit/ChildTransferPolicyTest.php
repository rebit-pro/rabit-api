<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ChildTransferPolicyTest extends TestCase
{
    public function testFreeCodesFollowThePrototypeOrderAndSkipTakenCodes(): void
    {
        $policy = new ChildTransferPolicy();

        self::assertSame(['A', 'B'], $policy->freeCodes([], 2));
        self::assertSame(['B', 'D'], $policy->freeCodes(['A', 'C'], 2));
        self::assertSame(['AA', 'AB'], $policy->freeCodes(range('A', 'Z'), 2));
        self::assertSame([], $policy->freeCodes(['A'], 0));
    }

    public function testThreeLetterCodesStartAfterAllTwoLetterCodes(): void
    {
        $taken = range('A', 'Z');
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $taken[] = $first . $second;
            }
        }

        self::assertSame(['AAA'], (new ChildTransferPolicy())->freeCodes($taken, 1));
    }

    public function testSetsAreComparedWithoutOrderButWithContent(): void
    {
        $policy = new ChildTransferPolicy();

        self::assertTrue($policy->sameSet(['b', 'a'], ['a', 'b']));
        self::assertFalse($policy->sameSet(['a'], ['a', 'b']));
        self::assertFalse($policy->sameSet(['a', 'c'], ['a', 'b']));
    }

    public function testSharedPhotosAreAssignedToAChildThatStays(): void
    {
        $shared = (new ChildTransferPolicy())->sharedPhotos([101 => [7], 102 => [7, 8], 103 => [7, 9]], [7 => true, 9 => true]);

        self::assertSame([102], $shared);
    }

    public function testCodesAndFrameCodesUseTheLabelingFormat(): void
    {
        $policy = new ChildTransferPolicy();

        self::assertTrue($policy->isCode('ABC'));
        self::assertFalse($policy->isCode('a'));
        self::assertFalse($policy->isCode('ABCD'));
        self::assertSame('B007', $policy->frameCode('B', 7));
        self::assertSame('AB120', $policy->frameCode('AB', 120));
    }
}
