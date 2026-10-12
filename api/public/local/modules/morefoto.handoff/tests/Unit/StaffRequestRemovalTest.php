<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Domain\Request\Enum\StaffRequestSortEnum;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestListRequestDto;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Withdrawal of a staff list and the server-side sort of the registry (#92 slice 2).
 *
 * @internal
 */
final class StaffRequestRemovalTest extends TestCase
{
    private const string REQUEST = '77777777-7777-4777-8777-777777777777';

    public function testAuthorWithdrawsListUnderReview(): void
    {
        $repository = $this->repository('submitted', createdBy: 7);
        $repository->expects(self::once())->method('delete')->with(50, self::REQUEST);

        $this->workflow($repository, $this->teacher())->remove(7, self::REQUEST);
    }

    public function testOrganizerWithdrawsListOfAnotherAuthor(): void
    {
        $repository = $this->repository('clarification', createdBy: 7);
        $repository->expects(self::once())->method('delete')->with(50, self::REQUEST);

        $this->workflow($repository, new StaffRequestActorOutputDto(12, 'Организатор', 'organizer', 1, [], []))->remove(12, self::REQUEST);
    }

    public function testTransferredListStaysAsTransferHistory(): void
    {
        $repository = $this->repository('transferred', createdBy: 7);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException('REQUEST_TRANSFERRED', 409));
        $this->workflow($repository, $this->teacher())->remove(7, self::REQUEST);
    }

    public function testCuratorAndHeadDoNotWithdraw(): void
    {
        foreach (['curator', 'head'] as $role) {
            $repository = $this->repository('submitted', createdBy: 7);
            $repository->expects(self::never())->method('delete');
            try {
                $this->workflow($repository, new StaffRequestActorOutputDto(9, 'Сотрудник', $role, 1, [10], []))->remove(9, self::REQUEST);
                self::fail($role . ' must not withdraw a staff list.');
            } catch (HttpException $exception) {
                self::assertSame('FORBIDDEN', $exception->getMessage());
            }
        }
    }

    public function testAnotherTeacherDoesNotSeeTheList(): void
    {
        $repository = $this->repository('submitted', createdBy: 8);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException('STAFF_REQUEST_NOT_FOUND', 404));
        $this->workflow($repository, $this->teacher())->remove(7, self::REQUEST);
    }

    public function testRegistrySortIsWhitelisted(): void
    {
        $mapper = new StaffRequestInputMapper();
        $default = $mapper->list(new StaffRequestListRequestDto());
        self::assertSame(StaffRequestSortEnum::UPDATED_AT, $default->sort);
        self::assertTrue($default->descending);

        $byAuthor = $mapper->list(new StaffRequestListRequestDto(sort: 'createdByName', direction: 'asc'));
        self::assertSame(StaffRequestSortEnum::CREATED_BY_NAME, $byAuthor->sort);
        self::assertFalse($byAuthor->descending);

        foreach ([['sort' => 'ID'], ['direction' => 'up']] as $query) {
            try {
                $mapper->list(new StaffRequestListRequestDto(...$query));
                self::fail('An unknown sort must be refused.');
            } catch (HttpException $exception) {
                self::assertSame('INVALID_SORT', $exception->getMessage());
            }
        }
    }

    public function testRemovalControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffRequestRemovalController.php',
            [
                'Morefoto\Handoff\Presentation\Controller',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Morefoto\Handoff\Application\Request\UseCase\\', 'UseCase'],
                ['Morefoto\Handoff\Presentation\Request\Dto\\', 'RequestDto'],
            ],
        ));
    }

    private function teacher(): StaffRequestActorOutputDto
    {
        return new StaffRequestActorOutputDto(7, 'Воспитатель', 'teacher', 1, [], [30]);
    }

    private function repository(string $status, int $createdBy): MockObject
    {
        $repository = $this->createMock(StaffRequestRepository::class);
        $repository->method('request')->with(self::REQUEST, true)
            ->willReturn(['ID' => 50, 'INSTITUTION_ID' => 10, 'CREATED_BY' => $createdBy, 'STATUS' => $status])
        ;
        $repository->method('groupIds')->willReturn([30]);

        return $repository;
    }

    private function workflow(MockObject $repository, StaffRequestActorOutputDto $actor): StaffRequestWorkflow
    {
        self::assertInstanceOf(StaffRequestRepository::class, $repository);
        $access = $this->createStub(StaffRequestAccessInterface::class);
        $access->method('actor')->willReturn($actor);
        $transaction = new class implements HandoffTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };

        return new StaffRequestWorkflow(
            $transaction,
            $repository,
            $access,
            $this->createStub(MediaScopeInterface::class),
            $this->createStub(StaffChildReferenceInterface::class),
        );
    }
}
