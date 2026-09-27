<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Structure\UseCase\DeleteStructureUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Enum\StructureKindEnum;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\StructureSalesRemovalInterface;
use Rebit\Share\Contracts\Handoff\StructureHandoffRemovalInterface;
use Rebit\Share\Contracts\Media\Dto\RemovedMediaFilesDto;
use Rebit\Share\Contracts\Media\StructureMediaRemovalInterface;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;
use Rebit\Share\Contracts\Support\StructureSupportRemovalInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class DeleteStructureUseCaseTest extends TestCase
{
    private const string ID = '12345678-abcd-4abc-8abc-123456789abc';
    private const int ACTOR = 1;

    /** @var list<string> */
    private array $calls = [];
    private bool $committed = false;
    private ?StructureRemovalDto $removal = null;

    public function testGroupGoesWithEverythingItOwnsAndItsFilesAfterCommit(): void
    {
        $structure = $this->structure(
            target: ['ID' => 30, 'UF_SHOOT_ID' => 20, 'UF_INSTITUTION_ID' => 10],
            shoots: [],
            groups: [['ID' => 30, 'UF_NAME' => 'Средняя', 'UF_REVISION' => 4]],
        );
        $structure->expects(self::once())->method('lockShoot')->with(20)->willReturn($this->rowsOf([['ID' => 20]]));
        $structure->expects(self::never())->method('lockRemovedShoots');
        $structure->expects(self::once())->method('lockRemovedGroups')->with(10, 20, 30);
        $structure->expects(self::once())->method('delete')->with([30], [], [])
            ->willReturnCallback(function(): void { $this->calls[] = 'delete'; })
        ;
        $operations = $this->createMock(InstitutionOperationRepository::class);
        $operations->expects(self::once())->method('record')
            ->with(30, 4, 5, self::ACTOR, self::matchesRegularExpression('/^[0-9a-f-]{36}$/D'), '{"deleted":true,"name":"Средняя"}', 'group')
        ;
        $groupAccess = $this->groupAccess($this->createMock(GroupAccessInterface::class), [30 => new GroupAssignmentOutputDto(7)]);
        $groupAccess->expects(self::once())->method('lockParticipants')->with(self::ACTOR, 'bearer', [7]);
        $groupAccess->expects(self::once())->method('replace')
            ->with(30, new GroupAssignmentOutputDto(), 'sig', true, self::ACTOR, self::anything(), 'Группа удалена')
            ->willReturnCallback(function(): string {
                $this->calls[] = 'unassign';

                return 'sig';
            })
        ;

        $this->useCase($structure, $operations, $groupAccess)->execute(self::ACTOR, 'bearer', StructureKindEnum::GROUP, new StructureId(self::ID));

        self::assertEquals(new StructureRemovalDto([30], [], []), $this->removal);
        self::assertSame(['sales', 'handoff', 'support', 'media', 'unassign', 'delete', 'commit', 'files'], $this->calls);
    }

    public function testInstitutionTakesAllShootsGroupsAndItsStaffAssignments(): void
    {
        $structure = $this->structure(
            target: ['ID' => 10],
            shoots: [['ID' => 20, 'UF_NAME' => 'Осень', 'UF_REVISION' => 1], ['ID' => 21, 'UF_NAME' => 'Весна', 'UF_REVISION' => 2]],
            groups: [['ID' => 30, 'UF_NAME' => 'А', 'UF_REVISION' => 1]],
        );
        $structure->expects(self::once())->method('lockRemovedShoots')->with(10, null);
        $structure->expects(self::once())->method('lockRemovedGroups')->with(10, null, null);
        $structure->expects(self::once())->method('delete')->with([30], [20, 21], [10]);
        $operations = $this->createMock(InstitutionOperationRepository::class);
        $operations->expects(self::exactly(4))->method('record');
        $institutionAccess = $this->institutionAccess($this->createMock(InstitutionAccessInterface::class), new InstitutionAssignmentOutputDto(curatorId: 5));
        $institutionAccess->expects(self::once())->method('replace')->with(10, new InstitutionAssignmentOutputDto(), 'sig', true, self::ACTOR, self::anything());
        $groupAccess = $this->groupAccess($this->createMock(GroupAccessInterface::class), []);
        $groupAccess->expects(self::once())->method('lockParticipants')->with(self::ACTOR, 'bearer', [5]);
        $groupAccess->expects(self::never())->method('replace');

        $this->useCase($structure, $operations, $groupAccess, $institutionAccess)->execute(self::ACTOR, 'bearer', StructureKindEnum::INSTITUTION, new StructureId(self::ID));

        self::assertEquals(new StructureRemovalDto([30], [20, 21], [10]), $this->removal);
    }

    public function testOrdersRefuseBeforeAnythingIsRemoved(): void
    {
        $structure = $this->structure(
            target: ['ID' => 20, 'UF_INSTITUTION_ID' => 10],
            shoots: [['ID' => 20, 'UF_NAME' => 'Осень', 'UF_REVISION' => 1]],
            groups: [],
        );
        $structure->expects(self::never())->method('delete');
        $sales = $this->createStub(StructureSalesRemovalInterface::class);
        $sales->method('remove')->willThrowException(new HttpException('STRUCTURE_HAS_ORDERS', 409));
        $media = $this->createMock(StructureMediaRemovalInterface::class);
        $media->expects(self::never())->method('remove');
        $media->expects(self::never())->method('removeFiles');

        try {
            $this->useCase($structure, sales: $sales, media: $media)->execute(self::ACTOR, 'bearer', StructureKindEnum::SHOOT, new StructureId(self::ID));
            self::fail('Expected STRUCTURE_HAS_ORDERS');
        } catch (HttpException $error) {
            self::assertSame(['STRUCTURE_HAS_ORDERS', 409], [$error->getMessage(), $error->getCode()]);
        }
        self::assertFalse($this->committed);
    }

    public function testOnlyOrganizerRemoves(): void
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->expects(self::never())->method('group');

        $this->expectExceptionObject(new HttpException('FORBIDDEN', 403));
        $this->useCase($structure, institutionAccess: $this->institutionAccess($this->createStub(InstitutionAccessInterface::class), new InstitutionAssignmentOutputDto(), 'curator'))
            ->execute(self::ACTOR, 'bearer', StructureKindEnum::GROUP, new StructureId(self::ID))
        ;
    }

    public function testMissingGroupIsNotFound(): void
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->method('group')->willReturn($this->rowsOf([]));
        $structure->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException('NOT_FOUND', 404));
        $this->useCase($structure)->execute(self::ACTOR, 'bearer', StructureKindEnum::GROUP, new StructureId(self::ID));
    }

    /**
     * @param array<string, int>                                      $target
     * @param list<array{ID: int, UF_NAME: string, UF_REVISION: int}> $shoots
     * @param list<array{ID: int, UF_NAME: string, UF_REVISION: int}> $groups
     */
    private function structure(array $target, array $shoots, array $groups): MockObject
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->method('group')->willReturn($this->rowsOf([$target]));
        $structure->method('shoot')->willReturn($this->rowsOf([$target]));
        $structure->method('institution')->willReturn($this->rowsOf([$target]));
        $structure->method('lockInstitution')->willReturn($this->rowsOf([['ID' => 10, 'UF_NAME' => 'Сад', 'UF_REVISION' => 3]]));
        $structure->method('lockRemovedShoots')->willReturn($this->rowsOf($shoots));
        $structure->method('lockRemovedGroups')->willReturn($this->rowsOf($groups));

        return $structure;
    }

    /** @param list<array<string, int|string>> $rows */
    private function rowsOf(array $rows): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);

        return $result;
    }

    /**
     * @template T of Stub&GroupAccessInterface
     *
     * @param T                                    $access
     * @param array<int, GroupAssignmentOutputDto> $teachers
     *
     * @return T
     */
    private function groupAccess(GroupAccessInterface&Stub $access, array $teachers): GroupAccessInterface&Stub
    {
        $access->method('assignments')->willReturn($teachers);
        $access->method('signature')->willReturn('sig');

        return $access;
    }

    /**
     * @template T of Stub&InstitutionAccessInterface
     *
     * @param T $access
     *
     * @return T
     */
    private function institutionAccess(InstitutionAccessInterface&Stub $access, InstitutionAssignmentOutputDto $heads, string $role = 'organizer'): InstitutionAccessInterface&Stub
    {
        $access->method('scope')->willReturn(new InstitutionScopeOutputDto($role, 1, []));
        $access->method('assignments')->willReturn([10 => $heads]);
        $access->method('signature')->willReturn('sig');

        return $access;
    }

    private function useCase(
        MockObject $structure,
        ?InstitutionOperationRepository $operations = null,
        ?GroupAccessInterface $groupAccess = null,
        ?InstitutionAccessInterface $institutionAccess = null,
        ?StructureSalesRemovalInterface $sales = null,
        ?StructureMediaRemovalInterface $media = null,
    ): DeleteStructureUseCase {
        $record = fn(string $name): \Closure => function(StructureRemovalDto $removal) use ($name): void {
            $this->removal = $removal;
            $this->calls[] = $name;
        };
        if (null === $sales) {
            $sales = $this->createStub(StructureSalesRemovalInterface::class);
            $sales->method('remove')->willReturnCallback($record('sales'));
        }
        $handoff = $this->createStub(StructureHandoffRemovalInterface::class);
        $handoff->method('remove')->willReturnCallback($record('handoff'));
        $support = $this->createStub(StructureSupportRemovalInterface::class);
        $support->method('remove')->willReturnCallback($record('support'));
        if (null === $media) {
            $media = $this->createStub(StructureMediaRemovalInterface::class);
            $media->method('remove')->willReturnCallback(function(): RemovedMediaFilesDto {
                $this->calls[] = 'media';

                return new RemovedMediaFilesDto([['photoId' => 'p', 'originalPath' => 'o']]);
            });
            $media->method('removeFiles')->willReturnCallback(function(): void { $this->calls[] = 'files'; });
        }
        $transaction = $this->createStub(InstitutionTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(function(callable $operation): mixed {
            $result = $operation();
            $this->committed = true;
            $this->calls[] = 'commit';

            return $result;
        });

        self::assertInstanceOf(StructureRepository::class, $structure);

        return new DeleteStructureUseCase(
            $structure,
            $operations ?? $this->createStub(InstitutionOperationRepository::class),
            $institutionAccess ?? $this->institutionAccess($this->createStub(InstitutionAccessInterface::class), new InstitutionAssignmentOutputDto()),
            $groupAccess ?? $this->groupAccess($this->createStub(GroupAccessInterface::class), []),
            $sales,
            $handoff,
            $support,
            $media,
            $transaction,
        );
    }
}
