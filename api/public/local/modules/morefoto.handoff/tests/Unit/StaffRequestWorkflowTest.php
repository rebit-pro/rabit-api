<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Dto\ClarificationInputDto;
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
        $repository->expects(self::once())->method('reserveIdempotency')->with(7, '/staff-requests', str_repeat('a', 32), self::isString())->willReturn(true);
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
        $repository->expects(self::once())->method('completeIdempotency')->with(7, '/staff-requests', str_repeat('a', 32), self::stringContains('"revision":1'));
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
        $repository->method('reserveIdempotency')->willReturn(true);
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

    /** #27: a concurrent twin committed first; after the wait on the key it replays without touching the request. */
    public function testConcurrentUpdateWithTheSameKeyReplaysAfterTheKeyWait(): void
    {
        $input = $this->update();
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->expects(self::once())->method('reserveIdempotency')->willReturn(false);
        $repository->expects(self::once())->method('idempotency')
            ->with(7, '/staff-requests/77777777-7777-4777-8777-777777777777', str_repeat('d', 32), false)
            ->willReturn(['PAYLOAD_HASH' => $this->updateHash($input), 'RESULT_JSON' => '{"id":"77777777-7777-4777-8777-777777777777","revision":4,"status":"submitted"}'])
        ;
        $repository->expects(self::never())->method('request');
        $repository->expects(self::never())->method('resubmit');
        $repository->expects(self::never())->method('appendHistory');
        $repository->expects(self::never())->method('completeIdempotency');

        $result = $this->workflow($repository, 30)->save(7, '77777777-7777-4777-8777-777777777777', new IdempotencyKey(str_repeat('d', 32)), $input);

        self::assertSame(['77777777-7777-4777-8777-777777777777', 4, 'submitted'], [$result->id, $result->revision, $result->status]);
    }

    public function testConcurrentClarificationWithTheSameKeyReplays(): void
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->expects(self::once())->method('reserveIdempotency')->willReturn(false);
        $repository->method('idempotency')->willReturn([
            'PAYLOAD_HASH' => hash('sha256', json_encode(['77777777-7777-4777-8777-777777777777', 3, 'Уточните код', true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'RESULT_JSON' => '{"id":"77777777-7777-4777-8777-777777777777","revision":4,"status":"clarification"}',
        ]);
        $repository->expects(self::never())->method('request');
        $repository->expects(self::never())->method('clarify');
        $repository->expects(self::never())->method('appendHistory');
        $curator = new StaffRequestActorOutputDto(9, 'Куратор', 'curator', 1, [10], []);

        $result = $this->workflow($repository, 30, $curator)->clarify(9, '77777777-7777-4777-8777-777777777777', new IdempotencyKey(str_repeat('e', 32)), new ClarificationInputDto(3, 'Уточните код', true));

        self::assertSame([4, 'clarification'], [$result->revision, $result->status]);
    }

    public function testConcurrentCreateWithTheSameKeyDoesNotCreateASecondRequest(): void
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->method('reserveIdempotency')->willReturn(false);
        $repository->method('idempotency')->willReturn([
            'PAYLOAD_HASH' => $this->createHash(),
            'RESULT_JSON' => '{"id":"88888888-8888-4888-8888-888888888888","revision":1,"status":"submitted"}',
        ]);
        $repository->expects(self::never())->method('create');

        $result = $this->workflow($repository, 30)->save(7, null, new IdempotencyKey(str_repeat('a', 32)), $this->create());

        self::assertSame('88888888-8888-4888-8888-888888888888', $result->id);
    }

    public function testSameKeyWithAnotherBodyIsAConflict(): void
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->method('reserveIdempotency')->willReturn(false);
        $repository->method('idempotency')->willReturn(['PAYLOAD_HASH' => str_repeat('0', 64), 'RESULT_JSON' => '{}']);
        $repository->expects(self::never())->method('resubmit');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $this->workflow($repository, 30)->save(7, '77777777-7777-4777-8777-777777777777', new IdempotencyKey(str_repeat('d', 32)), $this->update());
    }

    public function testKeyIsReservedBeforeTheRequestIsLocked(): void
    {
        $calls = [];
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->method('reserveIdempotency')->willReturnCallback(static function() use (&$calls): bool {
            $calls[] = 'reserve';

            return true;
        });
        $repository->method('request')->willReturnCallback(static function(string $id, bool $lock) use (&$calls): array {
            $calls[] = $lock ? 'lock' : 'read';

            return ['ID' => 50, 'PUBLIC_ID' => $id, 'INSTITUTION_ID' => 10, 'CREATED_BY' => 7, 'STATUS' => 'clarification', 'REVISION' => 3,
                'INSTITUTION_PUBLIC_ID' => '11111111-1111-4111-8111-111111111111', 'SHOOT_PUBLIC_ID' => '22222222-2222-4222-8222-222222222222'];
        });
        $repository->method('groupIds')->willReturn([30]);
        $repository->method('activeChildRequest')->willReturn(false);
        $repository->method('resubmit')->willReturn(4);
        $repository->expects(self::once())->method('completeIdempotency');

        $result = $this->workflow($repository, 30)->save(7, '77777777-7777-4777-8777-777777777777', new IdempotencyKey(str_repeat('d', 32)), $this->update());

        self::assertSame(['reserve', 'lock'], $calls);
        self::assertSame(4, $result->revision);
    }

    private function create(): StaffRequestMutationInputDto
    {
        return new StaffRequestMutationInputDto(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            [['id' => '33333333-3333-4333-8333-333333333333', 'groupId' => '44444444-4444-4444-8444-444444444444', 'code' => 'A001']],
            'Список группы',
            null,
        );
    }

    private function update(): StaffRequestMutationInputDto
    {
        return new StaffRequestMutationInputDto(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            [['id' => '33333333-3333-4333-8333-333333333333', 'groupId' => '44444444-4444-4444-8444-444444444444', 'code' => 'A001']],
            'Исправлено',
            3,
        );
    }

    private function createHash(): string
    {
        return $this->hash(null, $this->create());
    }

    private function updateHash(StaffRequestMutationInputDto $input): string
    {
        return $this->hash('77777777-7777-4777-8777-777777777777', $input);
    }

    /** Mirrors the payload fingerprint of the workflow: the tests prove replay, not the hash format. */
    private function hash(?string $requestId, StaffRequestMutationInputDto $input): string
    {
        return hash('sha256', json_encode([
            'requestId' => $requestId,
            'institutionId' => $input->institutionId,
            'shootId' => $input->shootId,
            'rows' => $input->rows,
            'comment' => $input->comment,
            'revision' => $input->revision,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
