<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Bitrix\Main\DB\Result;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Enum\StructureKindEnum;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\StructureSalesRemovalInterface;
use Rebit\Share\Contracts\Handoff\StructureHandoffRemovalInterface;
use Rebit\Share\Contracts\Media\Dto\RemovedMediaFilesDto;
use Rebit\Share\Contracts\Media\StructureMediaRemovalInterface;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;
use Rebit\Share\Contracts\Support\StructureSupportRemovalInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Безвозвратно удаляет группу, съёмку или учреждение организатора вместе с кадрами, детьми, ссылками, условиями
 * продажи, списками сотрудников и назначениями, пока по ним нет заказов. Файлы кадров стираются после фиксации.
 */
final readonly class DeleteStructureUseCase
{
    private const string UNASSIGN_REASON = 'Группа удалена';

    public function __construct(
        private StructureRepository $structure,
        private InstitutionOperationRepository $operations,
        private InstitutionAccessInterface $institutionAccess,
        private GroupAccessInterface $groupAccess,
        private StructureSalesRemovalInterface $sales,
        private StructureHandoffRemovalInterface $handoff,
        private StructureSupportRemovalInterface $support,
        private StructureMediaRemovalInterface $media,
        private InstitutionTransactionInterface $transaction,
    ) {}

    public function execute(int $actor, string $bearer, StructureKindEnum $kind, StructureId $id): void
    {
        $files = $this->transaction->execute(fn(): RemovedMediaFilesDto => $this->remove($actor, $bearer, $kind, $id));
        $this->media->removeFiles($files);
    }

    private function remove(int $actor, string $bearer, StructureKindEnum $kind, StructureId $id): RemovedMediaFilesDto
    {
        $this->groupAccess->lockState();
        if ('organizer' !== $this->institutionAccess->scope($actor)->role) {
            throw new HttpException('FORBIDDEN', 403);
        }
        /** @var array{
         *     ID: int|string,
         *     UF_INSTITUTION_ID?: int|string,
         *     UF_SHOOT_ID?: int|string,
         * }|false $target */
        $target = match ($kind) {
            StructureKindEnum::GROUP => $this->structure->group($id)->fetch(),
            StructureKindEnum::SHOOT => $this->structure->shoot($id)->fetch(),
            StructureKindEnum::INSTITUTION => $this->structure->institution($id)->fetch(),
        };
        if (false === $target) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $institutionId = StructureKindEnum::INSTITUTION === $kind ? (int)$target['ID'] : (int)($target['UF_INSTITUTION_ID'] ?? 0);
        /** @var array{ID: int|string, UF_NAME: string, UF_REVISION: int|string}|false $institution */
        $institution = $this->structure->lockInstitution($institutionId)->fetch();
        $shootId = match ($kind) {
            StructureKindEnum::GROUP => (int)($target['UF_SHOOT_ID'] ?? 0),
            StructureKindEnum::SHOOT => (int)$target['ID'],
            StructureKindEnum::INSTITUTION => null,
        };
        // A removed group keeps its shoot: the shoot is only locked, in the same order as group edits take it.
        if (false === $institution || (StructureKindEnum::GROUP === $kind && false === $this->structure->lockShoot((int)$shootId)->fetch())) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $shoots = StructureKindEnum::GROUP === $kind ? [] : $this->rows($this->structure->lockRemovedShoots($institutionId, $shootId));
        $groups = $this->rows($this->structure->lockRemovedGroups($institutionId, $shootId, StructureKindEnum::GROUP === $kind ? (int)$target['ID'] : null));
        if ((StructureKindEnum::SHOOT === $kind && [] === $shoots) || (StructureKindEnum::GROUP === $kind && [] === $groups)) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $groupIds = array_keys($groups);
        $teachers = [] === $groupIds ? [] : $this->groupAccess->assignments($groupIds);
        $heads = StructureKindEnum::INSTITUTION === $kind
            ? ($this->institutionAccess->assignments([$institutionId])[$institutionId] ?? new InstitutionAssignmentOutputDto())
            : new InstitutionAssignmentOutputDto();
        $users = [];
        foreach ([...array_map(static fn(GroupAssignmentOutputDto $teacher): ?int => $teacher->teacherId, $teachers), $heads->curatorId, $heads->headId] as $user) {
            if (null !== $user) {
                $users[] = $user;
            }
        }
        $this->groupAccess->lockParticipants($actor, $bearer, $users);
        $institutions = StructureKindEnum::INSTITUTION === $kind ? [$institutionId] : [];
        $removal = new StructureRemovalDto($groupIds, array_keys($shoots), $institutions);
        // Commerce refuses first when orders exist; its carts and Handoff's staff rows hold keys that Media deletes next.
        $this->sales->remove($removal);
        $this->handoff->remove($removal);
        $this->support->remove($removal);
        $files = $this->media->remove($removal);
        $operationId = Uuid::uuid4()->toString();
        foreach ($teachers as $groupId => $teacher) {
            if (null !== $teacher->teacherId) {
                $this->groupAccess->replace($groupId, new GroupAssignmentOutputDto(), $this->groupAccess->signature(), true, $actor, $operationId, self::UNASSIGN_REASON);
            }
        }
        if (null !== $heads->curatorId || null !== $heads->headId) {
            $this->institutionAccess->replace($institutionId, new InstitutionAssignmentOutputDto(), $this->institutionAccess->signature(), true, $actor, $operationId);
        }
        $this->record($groups, 'group', $actor, $operationId);
        $this->record($shoots, 'shoot', $actor, $operationId);
        if ([] !== $institutions) {
            $this->record([$institutionId => $institution], 'institution', $actor, $operationId);
        }
        $this->structure->delete($groupIds, array_keys($shoots), $institutions);

        return $files;
    }

    /** @return array<int, array{ID: int|string, UF_NAME: string, UF_REVISION: int|string}> keyed by native ID */
    private function rows(Result $result): array
    {
        $rows = [];
        while (false !== ($row = $result->fetch())) {
            /** @var array{ID: int|string, UF_NAME: string, UF_REVISION: int|string} $row */
            $rows[(int)$row['ID']] = $row;
        }

        return $rows;
    }

    /** @param array<int, array{ID: int|string, UF_NAME: string, UF_REVISION: int|string}> $rows */
    private function record(array $rows, string $type, int $actor, string $operationId): void
    {
        foreach ($rows as $id => $row) {
            $revision = (int)$row['UF_REVISION'];
            $this->operations->record(
                id: $id,
                from: $revision,
                to: $revision + 1,
                actor: $actor,
                operationId: $operationId,
                delta: json_encode(['deleted' => true, 'name' => $row['UF_NAME']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                aggregateType: $type,
            );
        }
    }
}
