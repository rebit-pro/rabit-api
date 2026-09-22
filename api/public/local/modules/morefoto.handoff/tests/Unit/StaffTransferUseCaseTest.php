<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferPlanOutputDto;
use Morefoto\Handoff\Application\Request\Service\StaffTransferGuard;
use Morefoto\Handoff\Application\Request\Service\StaffTransferPlanner;
use Morefoto\Handoff\Application\Request\UseCase\ConfirmStaffTransferUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffTransferPreviewUseCase;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Morefoto\Handoff\Domain\Request\ValueObject\StaffTransferPlan;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class StaffTransferUseCaseTest extends TestCase
{
    private const string REQUEST = '11111111-1111-4111-8111-111111111111';
    private const string SIGNATURE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testConfirmationMovesEveryBundleOnceAndRecordsTheResult(): void
    {
        $order = [];
        $access = $this->createMock(GroupLinkAccessInterface::class);
        $access->expects(self::once())->method('lockState')->willReturnCallback(static function() use (&$order): void {
            $order[] = 'access';
        });
        $access->method('lockActor')->willReturnCallback(static function() use (&$order): LinkActorOutputDto {
            $order[] = 'actor';

            return new LinkActorOutputDto(5, 'Организатор', 'organizer', [], []);
        });
        $calendar = $this->createMock(GroupCalendarInterface::class);
        $calendar->expects(self::exactly(3))->method('lock')->willReturnCallback(static function(string $group) use (&$order): int {
            $order[] = $group;

            return 1;
        });
        $children = $this->createMock(ChildTransferInterface::class);
        $children->expects(self::once())->method('move')->with(20, [new ChildMoveInputDto(7, 90, 'C'), new ChildMoveInputDto(8, 90, 'D')])->willReturn(12);
        $requests = $this->createMock(StaffRequestRepository::class);
        $this->configure($requests, 'submitted', null);
        $requests->expects(self::exactly(2))->method('recordTransfer');
        $requests->expects(self::once())->method('markTransferred')->with(50, 3)->willReturn(4);
        $requests->expects(self::once())->method('appendHistory')->with(50, 'transferred', 5, 'Организатор', 'Полные наборы перенесены в папку сотрудников. История заказов сохранена.', true);
        $requests->expects(self::once())->method('saveIdempotency');

        $output = $this->confirm($requests, $children, $access, $calendar)->execute(5, self::REQUEST, $this->key(), new StaffTransferInputDto('', 3, self::SIGNATURE));

        self::assertSame(['access', 'group-a', 'group-b', 'staff-group', 'actor'], $order);
        self::assertSame(4, $output->revision);
        self::assertSame('transferred', $output->status);
        self::assertSame('row-1', $output->results[0]['rowId']);
    }

    public function testStalePreviewTransfersNothing(): void
    {
        $children = $this->createMock(ChildTransferInterface::class);
        $children->expects(self::never())->method('move');
        $requests = $this->createMock(StaffRequestRepository::class);
        $this->configure($requests, 'submitted', null);
        $requests->expects(self::never())->method('markTransferred');

        $this->expectExceptionMessage('SIGNATURE_CONFLICT');
        $this->confirm($requests, $children)->execute(5, self::REQUEST, $this->key(), new StaffTransferInputDto('', 3, str_repeat('b', 64)));
    }

    public function testTransferredRequestReturnsItsResultWithoutMovingAgain(): void
    {
        $children = $this->createMock(ChildTransferInterface::class);
        $children->expects(self::never())->method('move');
        $requests = $this->createMock(StaffRequestRepository::class);
        $this->configure($requests, 'transferred', null);
        $requests->expects(self::never())->method('markTransferred');
        $requests->expects(self::once())->method('saveIdempotency');

        $output = $this->confirm($requests, $children)->execute(5, self::REQUEST, $this->key(), new StaffTransferInputDto('', 1, str_repeat('c', 64)));

        self::assertSame(3, $output->revision);
        self::assertSame('row-1', $output->results[0]['rowId']);
    }

    #[DataProvider('refusals')]
    public function testRefusalsTransferNothing(string $code, string $status, int $revision): void
    {
        $children = $this->createMock(ChildTransferInterface::class);
        $children->expects(self::never())->method('move');

        $this->expectExceptionMessage($code);
        $this->confirm($this->requests($status), $children)->execute(5, self::REQUEST, $this->key(), new StaffTransferInputDto('', $revision, self::SIGNATURE));
    }

    /** @return iterable<string, array{0: string, 1: string, 2: int}> */
    public static function refusals(): iterable
    {
        yield 'clarification requested' => ['REQUEST_NOT_SUBMITTED', 'clarification', 3];
        yield 'stale revision' => ['REVISION_CONFLICT', 'submitted', 2];
    }

    public function testTheSameKeyWithAnotherBodyConflicts(): void
    {
        $requests = $this->requests('submitted', ['PAYLOAD_HASH' => str_repeat('0', 64), 'RESULT_JSON' => '{}']);

        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $this->confirm($requests)->execute(5, self::REQUEST, $this->key(), new StaffTransferInputDto('', 3, self::SIGNATURE));
    }

    public function testCuratorOfAnotherInstitutionCannotSeeTheRequest(): void
    {
        $access = $this->createStub(GroupLinkAccessInterface::class);
        $access->method('actor')->willReturn(new LinkActorOutputDto(6, 'Куратор', 'curator', [2], []));
        $planner = $this->createMock(StaffTransferPlanner::class);
        $planner->expects(self::never())->method('plan');

        $this->expectExceptionMessage('STAFF_REQUEST_NOT_FOUND');
        (new GetStaffTransferPreviewUseCase(new StaffTransferGuard($access), $this->requests('submitted'), $planner))->execute(6, self::REQUEST);
    }

    #[DataProvider('previewRefusals')]
    public function testPreviewRefusals(string $code, LinkActorOutputDto|\Throwable $actor, string $status): void
    {
        $access = $this->createStub(GroupLinkAccessInterface::class);
        $actor instanceof \Throwable ? $access->method('actor')->willThrowException($actor) : $access->method('actor')->willReturn($actor);

        $this->expectExceptionMessage($code);
        (new GetStaffTransferPreviewUseCase(new StaffTransferGuard($access), $this->requests($status), $this->createStub(StaffTransferPlanner::class)))->execute(5, self::REQUEST);
    }

    /** @return iterable<string, array{0: string, 1: LinkActorOutputDto|\Throwable, 2: string}> */
    public static function previewRefusals(): iterable
    {
        yield 'teacher' => ['FORBIDDEN', new LinkActorOutputDto(7, 'Воспитатель', 'teacher', [], [30]), 'submitted'];
        yield 'text refusal of Access' => ['FORBIDDEN', new HttpException('Staff access is unavailable.', 403), 'submitted'];
        yield 'already transferred' => ['REQUEST_TRANSFERRED', new LinkActorOutputDto(5, 'Организатор', 'organizer', [], []), 'transferred'];
        yield 'waiting for clarification' => ['REQUEST_NOT_SUBMITTED', new LinkActorOutputDto(5, 'Организатор', 'organizer', [], []), 'clarification'];
    }

    private function confirm(
        StaffRequestRepository $requests,
        ?ChildTransferInterface $children = null,
        ?GroupLinkAccessInterface $access = null,
        ?GroupCalendarInterface $calendar = null,
    ): ConfirmStaffTransferUseCase {
        if (null === $access) {
            $access = $this->createStub(GroupLinkAccessInterface::class);
            $access->method('lockActor')->willReturn(new LinkActorOutputDto(5, 'Организатор', 'organizer', [], []));
        }
        $planner = $this->createStub(StaffTransferPlanner::class);
        $planner->method('staffGroups')->willReturn([new MediaGroupOutputDto(90, 'staff-group', 'Сотрудники', 'staff')]);
        $planner->method('plan')->willReturn(new StaffTransferPlanOutputDto(
            plan: new StaffTransferPlan([
                ['rowId' => 'row-1', 'groupId' => 'group-a', 'childCode' => 'A', 'targetCode' => 'C', 'hasOrders' => false, 'photos' => [['id' => 'photo-1', 'code' => 'A001', 'revision' => 1]]],
                ['rowId' => 'row-2', 'groupId' => 'group-b', 'childCode' => 'B', 'targetCode' => 'D', 'hasOrders' => true, 'photos' => [['id' => 'photo-2', 'code' => 'B001', 'revision' => 1]]],
            ], true, self::SIGNATURE),
            targetGroupId: 90,
            targetGroupPublicId: 'staff-group',
            rows: $this->rows(),
            sets: [],
        ));

        return new ConfirmStaffTransferUseCase(
            new class implements HandoffTransactionInterface {
                public function execute(callable $operation): mixed
                {
                    return $operation();
                }
            },
            $access,
            $calendar ?? $this->createStub(GroupCalendarInterface::class),
            new StaffTransferGuard($access),
            $requests,
            $planner,
            $children ?? $this->createStub(ChildTransferInterface::class),
        );
    }

    /** @param null|array{PAYLOAD_HASH: string, RESULT_JSON: string} $stored */
    private function requests(string $status, ?array $stored = null): StaffRequestRepository
    {
        $requests = $this->createStub(StaffRequestRepository::class);
        $this->configure($requests, $status, $stored);

        return $requests;
    }

    /** @param null|array{PAYLOAD_HASH: string, RESULT_JSON: string} $stored */
    private function configure(Stub $requests, string $status, ?array $stored): void
    {
        $requests->method('request')->willReturn([
            'ID' => 50, 'PUBLIC_ID' => self::REQUEST, 'INSTITUTION_ID' => 1, 'SHOOT_ID' => 20,
            'SHOOT_PUBLIC_ID' => 'shoot', 'STATUS' => $status, 'REVISION' => 3,
        ]);
        $requests->method('transferRows')->willReturn($this->rows());
        $requests->method('idempotency')->willReturn($stored);
        $requests->method('results')->willReturn([
            ['rowId' => 'row-1', 'fromGroupId' => 'group-a', 'fromChildCode' => 'A', 'targetGroupId' => 'staff-group', 'targetChildCode' => 'C', 'photoIds' => ['photo-1']],
        ]);
    }

    /** @return list<array{id: int, publicId: string, groupId: int, groupPublicId: string, childId: int, photoIds: list<string>}> */
    private function rows(): array
    {
        return [
            ['id' => 1, 'publicId' => 'row-1', 'groupId' => 30, 'groupPublicId' => 'group-a', 'childId' => 7, 'photoIds' => ['photo-1']],
            ['id' => 2, 'publicId' => 'row-2', 'groupId' => 31, 'groupPublicId' => 'group-b', 'childId' => 8, 'photoIds' => ['photo-2']],
        ];
    }

    private function key(): IdempotencyKey
    {
        return new IdempotencyKey(str_repeat('e', 32));
    }
}
