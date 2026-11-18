<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Structure\Dto\ImportGroupsInputDto;
use Morefoto\Organization\Application\Structure\UseCase\ImportGroupsUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ImportGroupsUseCaseTest extends TestCase
{
    private const string ID = '12345678-abcd-4abc-8abc-123456789abc';

    public function testReplayKeepsTheExistingGroupsWithoutCreatingAnotherSet(): void
    {
        self::assertSame([self::ID], $this->replay(true)->execute(1, $this->input()));
    }

    public function testReplayRefusesAReceiptWhoseGroupsAreNoLongerInTheShoot(): void
    {
        $useCase = $this->replay(false);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('IMPORT_GROUPS_UNAVAILABLE');
        $useCase->execute(1, $this->input());
    }

    private function input(): ImportGroupsInputDto
    {
        return new ImportGroupsInputDto(self::ID, self::ID, str_repeat('a', 32), 'bearer', ['1А']);
    }

    private function replay(bool $groupsExist): ImportGroupsUseCase
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->method('shoot')->willReturn($this->row(['ID' => 20, 'UF_INSTITUTION_ID' => 10, 'INSTITUTION_PUBLIC_ID' => self::ID]));
        $structure->method('lockInstitution')->willReturn($this->row(['ID' => 10]));
        $structure->method('lockShoot')->willReturn($this->row(['ID' => 20]));
        $structure->expects(self::once())->method('importedGroupsExist')->with(20, [self::ID])->willReturn($groupsExist);
        $structure->expects(self::never())->method('createGroup');
        $operations = $this->createMock(InstitutionOperationRepository::class);
        $operations->method('find')->willReturn($this->row([
            'payload_hash' => hash('sha256', json_encode(['1А'], JSON_THROW_ON_ERROR)),
            'result_json' => json_encode([self::ID], JSON_THROW_ON_ERROR),
        ]));
        $operations->expects(self::never())->method('save');
        $institutions = $this->createStub(InstitutionAccessInterface::class);
        $institutions->method('scope')->willReturn(new InstitutionScopeOutputDto('organizer', 1, []));
        $access = $this->createMock(GroupAccessInterface::class);
        $access->expects(self::once())->method('lockState');
        $access->expects(self::once())->method('lockParticipants')->with(1, 'bearer', []);
        $transaction = $this->createStub(InstitutionTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());

        return new ImportGroupsUseCase($structure, $operations, $institutions, $access, $transaction);
    }

    /** @param array<string, int|string> $value */
    private function row(array $value): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($value);

        return $result;
    }
}
