<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Structure\Dto\ImportGroupsInputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Создаёт для каждой папки архива отдельную группу выбранной съёмки. Повтор возвращает ранее выданные идентификаторы
 * для продолжения загрузки, только если все группы импорта по-прежнему существуют в этой съёмке.
 */
final readonly class ImportGroupsUseCase
{
    public function __construct(
        private StructureRepository $structure,
        private InstitutionOperationRepository $operations,
        private InstitutionAccessInterface $institutions,
        private GroupAccessInterface $access,
        private InstitutionTransactionInterface $transaction,
    ) {}

    /** @return list<string> */
    public function execute(int $actor, ImportGroupsInputDto $input): array
    {
        return $this->transaction->execute(function() use ($actor, $input): array {
            $this->access->lockState();
            if ('organizer' !== $this->institutions->scope($actor)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $row = $this->structure->shoot(new StructureId($input->shootId))->fetch();
            if (false === $row || $input->institutionId !== $row['INSTITUTION_PUBLIC_ID']) {
                throw new HttpException('SHOOT_NOT_FOUND', 404);
            }
            $shoot = (int)$row['ID'];
            if (false === $this->structure->lockInstitution((int)$row['UF_INSTITUTION_ID'])->fetch() || false === $this->structure->lockShoot($shoot)->fetch()) {
                throw new HttpException('SHOOT_NOT_FOUND', 404);
            }
            $this->access->lockParticipants($actor, $input->bearer, []);
            $operation = 'POST /shoots/' . $input->shootId . '/import-groups';
            $hash = hash('sha256', json_encode($input->groups, JSON_THROW_ON_ERROR));
            $previous = $this->operations->find($actor, $operation, $input->key)->fetch();
            if (false !== $previous) {
                if (!hash_equals((string)$previous['payload_hash'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                /** @var non-empty-list<string> $groupIds */
                $groupIds = json_decode((string)$previous['result_json'], true, 512, JSON_THROW_ON_ERROR);
                if (!$this->structure->importedGroupsExist($shoot, $groupIds)) {
                    throw new HttpException('IMPORT_GROUPS_UNAVAILABLE', 409);
                }

                return $groupIds;
            }

            $result = [];
            foreach ($input->groups as $name) {
                $id = Uuid::uuid4()->toString();
                $native = $this->structure->createGroup(new StructureId($id), $shoot, new StructureName($name), 'regular');
                $this->operations->record($native, 0, 1, $actor, Uuid::uuid4()->toString(), json_encode([
                    'before' => null,
                    'after' => ['shootId' => $shoot, 'name' => $name, 'groupKind' => 'regular', 'teacherId' => null],
                    'reason' => 'Импорт ZIP учреждения',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'group');
                $result[] = $id;
            }
            $this->operations->save($actor, $operation, $input->key, $hash, json_encode($result, JSON_THROW_ON_ERROR));

            return $result;
        });
    }
}
