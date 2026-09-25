<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\Dto\StaffChildOutputDto;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class StaffRequestWorkflowTest extends TestCase
{
    public function testTeacherCreatesVerifiedRequestOnlyInsideAssignedGroup(): void
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->method('idempotency')->willReturn(null);
        $repository->method('activeChildRequest')->willReturn(false);
        $repository->expects(self::once())->method('create')->with(
            self::isString(),
            10,
            20,
            self::callback(static fn(StaffRequestActorOutputDto $actor): bool => 'teacher' === $actor->role),
            'Список группы',
            self::callback(static fn(array $rows): bool => 30 === $rows[0]['groupId'] && 40 === $rows[0]['childId']),
        )->willReturn(50);
        $repository->expects(self::once())->method('appendHistory')->with(50, 'submitted', 7, 'Воспитатель', 'Список группы', true);
        $repository->expects(self::once())->method('saveIdempotency');
        $workflow = $this->workflow($repository, 30);
        $result = $workflow->save(7, null, new IdempotencyKey(str_repeat('a', 32)), new StaffRequestMutationInputDto(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            [['id' => '33333333-3333-4333-8333-333333333333', 'groupId' => '44444444-4444-4444-8444-444444444444', 'code' => 'A001']],
            'Список группы',
            null,
        ));

        self::assertSame(1, $result->revision);
        self::assertSame('submitted', $result->status);
    }

    public function testTeacherCannotBorrowAnUnassignedGroup(): void
    {
        $repository = $this->createStub(StaffRequestRepository::class);
        $workflow = $this->workflow($repository, 31);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_NOT_FOUND');
        $workflow->save(7, null, new IdempotencyKey(str_repeat('b', 32)), new StaffRequestMutationInputDto(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            [['id' => '33333333-3333-4333-8333-333333333333', 'groupId' => '44444444-4444-4444-8444-444444444444', 'code' => 'A']],
            '',
            null,
        ));
    }

    public function testHeadReadsTheListsOfOwnInstitutionOnly(): void
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->expects(self::once())->method('page')->with(
            self::callback(static fn(StaffRequestActorOutputDto $actor): bool => 'head' === $actor->role && [10] === $actor->institutionIds),
            null,
            null,
            null,
            25,
            0,
        )->willReturn(['items' => [], 'total' => 0, 'byStatus' => ['submitted' => 0, 'clarification' => 0, 'transferred' => 0]]);
        $repository->method('options')->willReturn(['institutions' => [], 'shoots' => [], 'groups' => []]);
        $repository->method('request')->willReturn(['ID' => 50, 'INSTITUTION_ID' => 11, 'CREATED_BY' => 7]);

        $workflow = $this->workflow($repository, 30, new StaffRequestActorOutputDto(8, 'Руководитель', 'head', 1, [10], []));
        self::assertSame('head', $workflow->list(8, new StaffRequestListInputDto(null, null, null, 1, 25))->scope['role']);

        try {
            $workflow->detail(8, '77777777-7777-4777-8777-777777777777');
            self::fail('A list of another institution must stay hidden from the head.');
        } catch (HttpException $exception) {
            self::assertSame('STAFF_REQUEST_NOT_FOUND', $exception->getMessage());
        }
    }

    public function testHeadDoesNotChangeLists(): void
    {
        $workflow = $this->workflow($this->createStub(StaffRequestRepository::class), 30, new StaffRequestActorOutputDto(8, 'Руководитель', 'head', 1, [10], []));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('FORBIDDEN');
        $workflow->save(8, null, new IdempotencyKey(str_repeat('c', 32)), new StaffRequestMutationInputDto(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            [['id' => '33333333-3333-4333-8333-333333333333', 'groupId' => '44444444-4444-4444-8444-444444444444', 'code' => 'A']],
            '',
            null,
        ));
    }

    private function workflow(StaffRequestRepository $repository, int $resolvedGroup, ?StaffRequestActorOutputDto $actor = null): StaffRequestWorkflow
    {
        $access = $this->createStub(StaffRequestAccessInterface::class);
        $access->method('actor')->willReturn($actor ?? new StaffRequestActorOutputDto(7, 'Воспитатель', 'teacher', 1, [], [30]));
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(
            institutionId: 10,
            shootId: 20,
            shootPublicId: '22222222-2222-4222-8222-222222222222',
            groupId: $resolvedGroup,
            groupPublicId: '44444444-4444-4444-8444-444444444444',
            groupEditable: true,
            institutionPublicId: '11111111-1111-4111-8111-111111111111',
            groupKind: 'regular',
        ));
        $children = $this->createStub(StaffChildReferenceInterface::class);
        $children->method('resolve')->willReturn(new StaffChildOutputDto(40, '55555555-5555-4555-8555-555555555555', 'A', ['66666666-6666-4666-8666-666666666666']));
        $transaction = new class implements HandoffTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };

        return new StaffRequestWorkflow($transaction, $repository, $access, $scopes, $children);
    }
}
