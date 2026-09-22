<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkProblemEnum;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkFacts;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class LinkPolicyTest extends TestCase
{
    public function testReadyGroupHasNoProblemsAndEveryProblemIsReported(): void
    {
        $policy = new LinkReadinessPolicy();
        self::assertSame([], $policy->evaluate($this->facts())->problems);
        self::assertSame(
            [LinkProblemEnum::NO_PHOTOS, LinkProblemEnum::PHOTOS_PROCESSING, LinkProblemEnum::NO_PRODUCTS, LinkProblemEnum::STAFF_REQUESTS_PENDING],
            $policy->evaluate($this->facts(readyPhotos: 0, processingPhotos: 2, activeProducts: 0, pendingRequests: [['12345678-1234-4234-8234-123456789abc', 1]]))->problems,
        );
        self::assertSame([LinkProblemEnum::UNASSIGNED_PHOTOS], $policy->evaluate($this->facts(unassignedPhotos: 1))->problems);
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('changes')]
    public function testAnyReviewedFactChangesTheSignature(array $change): void
    {
        $policy = new LinkReadinessPolicy();
        self::assertNotSame($policy->evaluate($this->facts())->signature, $policy->evaluate($this->facts(...$change))->signature);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function changes(): iterable
    {
        yield 'group renamed' => [['groupName' => 'Звёздочка']];
        yield 'teacher replaced' => [['teacherId' => 22]];
        yield 'photo or assignment changed' => [['materialsFingerprint' => str_repeat('b', 64)]];
        yield 'conditions changed' => [['salesFingerprint' => str_repeat('d', 64)]];
        yield 'staff request revised' => [['pendingRequests' => [['12345678-1234-4234-8234-123456789abc', 2]]]];
    }

    public function testPreparationCountsOnlyForTheCurrentSignatureWithoutProblems(): void
    {
        $policy = new LinkReadinessPolicy();
        $readiness = $policy->evaluate($this->facts());
        self::assertTrue($policy->prepared(false, new LinkState(2, $readiness->signature), $readiness));
        self::assertFalse($policy->prepared(false, new LinkState(), $readiness));
        self::assertFalse($policy->prepared(false, new LinkState(2, str_repeat('e', 64)), $readiness));
        $blocked = $policy->evaluate($this->facts(processingPhotos: 1));
        self::assertFalse($policy->prepared(false, new LinkState(2, $blocked->signature), $blocked));
        self::assertTrue($policy->prepared(true, new LinkState(), $blocked));
    }

    public function testD08MatrixSeparatesVisibilityFromTheAllowedAction(): void
    {
        $policy = new LinkPermissionPolicy();
        self::assertTrue($policy->visible('organizer', [], [], 3, 10));
        self::assertTrue($policy->visible('curator', [3], [], 3, 10));
        self::assertFalse($policy->visible('head', [4], [], 3, 10));
        self::assertTrue($policy->visible('teacher', [], [10], 3, 10));
        self::assertFalse($policy->visible('teacher', [3], [11], 3, 10));
        self::assertFalse($policy->visible('unknown', [3], [10], 3, 10));
        $matrix = [];
        foreach (['organizer', 'curator', 'head', 'teacher'] as $role) {
            foreach (LinkActionEnum::cases() as $action) {
                $matrix[$role][$action->value] = $policy->allows($role, $action);
            }
        }
        self::assertSame([
            'organizer' => ['read' => true, 'prepare' => true, 'transmit' => true, 'correct' => true],
            'curator' => ['read' => true, 'prepare' => false, 'transmit' => true, 'correct' => true],
            'head' => ['read' => true, 'prepare' => false, 'transmit' => false, 'correct' => false],
            'teacher' => ['read' => true, 'prepare' => false, 'transmit' => true, 'correct' => false],
        ], $matrix);
        self::assertSame([null, null], $policy->scope('organizer', [3], [10]));
        self::assertSame([[3], null], $policy->scope('head', [3], []));
        self::assertSame([null, [10]], $policy->scope('teacher', [], [10]));
        self::assertSame([[], []], $policy->scope('unknown', [3], [10]));
    }

    public function testDeliveryCannotPrecedeTheMinuteTheLinkWasIssued(): void
    {
        $policy = new LinkDeliveryPolicy();
        $issued = new \DateTimeImmutable('2026-09-22T07:15:42Z');
        self::assertTrue($policy->acceptsSentAt(new \DateTimeImmutable('2026-09-22T10:15:00+03:00'), $issued));
        self::assertFalse($policy->acceptsSentAt(new \DateTimeImmutable('2026-09-22T10:14:59+03:00'), $issued));
        self::assertTrue($policy->acceptsSentAt(new \DateTimeImmutable('2026-09-23T08:00:00+03:00'), $issued));
    }

    /** @param list<array{0: string, 1: int}> $pendingRequests */
    private function facts(
        string $groupName = 'Солнышко',
        ?int $teacherId = 21,
        int $readyPhotos = 12,
        int $processingPhotos = 0,
        int $unassignedPhotos = 0,
        string $materialsFingerprint = '',
        int $activeProducts = 3,
        string $salesFingerprint = '',
        array $pendingRequests = [],
    ): LinkFacts {
        return new LinkFacts(
            groupId: '22345678-abcd-4abc-8abc-123456789abc',
            groupName: $groupName,
            groupKind: 'regular',
            shootId: '12345678-abcd-4abc-8abc-123456789abc',
            institutionId: '32345678-abcd-4abc-8abc-123456789abc',
            teacherId: $teacherId,
            readyPhotos: $readyPhotos,
            processingPhotos: $processingPhotos,
            unassignedPhotos: $unassignedPhotos,
            materialsFingerprint: '' === $materialsFingerprint ? str_repeat('a', 64) : $materialsFingerprint,
            activeProducts: $activeProducts,
            salesFingerprint: '' === $salesFingerprint ? str_repeat('c', 64) : $salesFingerprint,
            pendingRequests: $pendingRequests,
        );
    }
}
