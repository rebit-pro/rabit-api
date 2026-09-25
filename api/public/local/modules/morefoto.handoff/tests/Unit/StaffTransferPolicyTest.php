<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Domain\Request\Service\StaffTransferPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetPhotoOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class StaffTransferPolicyTest extends TestCase
{
    public function testBundlesKeepRowOrderTakeTheCurrentSetAndReportOrdersPerChild(): void
    {
        $plan = (new StaffTransferPolicy())->plan('request', 3, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'D'], [8 => true]);

        self::assertSame(['row-1', 'row-2'], array_column($plan->bundles, 'rowId'));
        self::assertSame(['C', 'D'], array_column($plan->bundles, 'targetCode'));
        self::assertSame([false, true], array_column($plan->bundles, 'hasOrders'));
        self::assertTrue($plan->hasOrders);
        self::assertSame(
            [['id' => 'photo-1', 'code' => 'A001', 'revision' => 1], ['id' => 'photo-3', 'code' => 'A002', 'revision' => 1]],
            $plan->bundles[0]['photos'],
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $plan->signature);
    }

    public function testSignatureFollowsEveryCheckedFact(): void
    {
        $policy = new StaffTransferPolicy();
        $base = $policy->plan('request', 3, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'D'], [])->signature;
        $changedPhoto = $this->sets();
        $changedPhoto[7] = new ChildSetOutputDto(7, 'child-7', 30, 'A', [
            new ChildSetPhotoOutputDto(1, 'photo-1', 'A001', 2, 'ready'),
            new ChildSetPhotoOutputDto(3, 'photo-3', 'A002', 1, 'ready'),
        ], []);

        self::assertSame($base, $policy->plan('request', 3, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'D'], [])->signature);
        self::assertNotSame($base, $policy->plan('request', 4, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'D'], [])->signature);
        self::assertNotSame($base, $policy->plan('request', 3, 'staff-group', 'open', $this->rows(), $changedPhoto, ['C', 'D'], [])->signature);
        self::assertNotSame($base, $policy->plan('request', 3, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'E'], [])->signature);
        self::assertNotSame($base, $policy->plan('request', 3, 'staff-group', 'open', $this->rows(), $this->sets(), ['C', 'D'], [7 => true])->signature);
        self::assertNotSame($base, $policy->plan('request', 3, 'staff-group', 'preparing', $this->rows(), $this->sets(), ['C', 'D'], [])->signature);
    }

    /** @param callable(array<int, ChildSetOutputDto>): array<int, ChildSetOutputDto> $change */
    #[DataProvider('staleSets')]
    public function testStaleOrSharedSetsAreRefused(string $code, callable $change): void
    {
        try {
            (new StaffTransferPolicy())->plan('request', 3, 'staff-group', 'open', $this->rows(), $change($this->sets()), ['C', 'D'], []);
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
            self::assertSame('SHARED_PHOTO' === $code ? ['photoCodes' => ['B001']] : [], $error->getDetails());
        }
    }

    /** @return iterable<string, array{0: string, 1: callable(array<int, ChildSetOutputDto>): array<int, ChildSetOutputDto>}> */
    public static function staleSets(): iterable
    {
        yield 'child moved away' => ['SET_CHANGED', static function(array $sets): array {
            unset($sets[8]);

            return $sets;
        }];
        yield 'child in another group' => ['SET_CHANGED', static fn(array $sets): array => [7 => $sets[7], 8 => new ChildSetOutputDto(8, 'child-8', 99, 'B', $sets[8]->photos, [])]];
        yield 'empty set' => ['SET_CHANGED', static fn(array $sets): array => [7 => $sets[7], 8 => new ChildSetOutputDto(8, 'child-8', 31, 'B', [], [])]];
        yield 'submitted frame lost' => ['SET_CHANGED', static fn(array $sets): array => [7 => new ChildSetOutputDto(7, 'child-7', 30, 'A', [$sets[7]->photos[1]], []), 8 => $sets[8]]];
        yield 'frame shared with a child outside the request' => ['SHARED_PHOTO', static fn(array $sets): array => [7 => $sets[7], 8 => new ChildSetOutputDto(8, 'child-8', 31, 'B', $sets[8]->photos, ['B001'])]];
    }

    public function testClosedTargetIsRefused(): void
    {
        $this->expectExceptionMessage('TARGET_GROUP_CLOSED');
        (new StaffTransferPolicy())->plan('request', 3, 'staff-group', 'closed', $this->rows(), $this->sets(), ['C', 'D'], []);
    }

    /** @return list<array{id: int, publicId: string, groupId: int, groupPublicId: string, childId: int, photoIds: list<string>}> */
    private function rows(): array
    {
        return [
            ['id' => 1, 'publicId' => 'row-1', 'groupId' => 30, 'groupPublicId' => 'group-30', 'childId' => 7, 'photoIds' => ['photo-1']],
            ['id' => 2, 'publicId' => 'row-2', 'groupId' => 31, 'groupPublicId' => 'group-31', 'childId' => 8, 'photoIds' => ['photo-2']],
        ];
    }

    /** @return array<int, ChildSetOutputDto> */
    private function sets(): array
    {
        return [
            7 => new ChildSetOutputDto(7, 'child-7', 30, 'A', [
                new ChildSetPhotoOutputDto(1, 'photo-1', 'A001', 1, 'ready'),
                new ChildSetPhotoOutputDto(3, 'photo-3', 'A002', 1, 'ready'),
            ], []),
            8 => new ChildSetOutputDto(8, 'child-8', 31, 'B', [new ChildSetPhotoOutputDto(2, 'photo-2', 'B001', 1, 'ready')], []),
        ];
    }
}
