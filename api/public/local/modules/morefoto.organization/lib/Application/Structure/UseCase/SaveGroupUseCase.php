<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;
use Ramsey\Uuid\Uuid;

final readonly class SaveGroupUseCase
{
    public function __construct(private StructureRepository $structure, private InstitutionOperationRepository $operations, private InstitutionAccessInterface $institutionAccess, private GroupAccessInterface $access, private InstitutionTransactionInterface $transaction) {}

    /** On create target is a shoot UUID; on update it is a group UUID. */
    public function execute(int $actor, string $bearer, StructureId $target, bool $create, GroupMutationInputDto $input): GroupMutationOutputDto
    {
        if (($create && (null === $input->name || null === $input->groupKind || null !== $input->revision))
            || (!$create && (null !== $input->groupKind || null === $input->revision || (null === $input->name && !$input->teacherProvided)))) {
            throw new HttpException('INVALID_MUTATION', 422);
        }

        return $this->transaction->execute(function() use ($actor, $bearer, $target, $create, $input): GroupMutationOutputDto {
            $this->access->lockState();
            if ('organizer' !== $this->institutionAccess->scope($actor)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $row = $create ? $this->structure->shoot($target)->fetch() : $this->structure->group($target)->fetch();
            if (false === $row) {
                throw new HttpException('NOT_FOUND', 404);
            }
            if (false === $this->structure->lockInstitution((int)$row['UF_INSTITUTION_ID'])->fetch()) {
                throw new HttpException('NOT_FOUND', 404);
            }
            $shootId = $create ? (int)$row['ID'] : (int)$row['UF_SHOOT_ID'];
            if (false === $this->structure->lockShoot($shootId)->fetch()) {
                throw new HttpException('NOT_FOUND', 404);
            }
            $old = new GroupAssignmentOutputDto();
            if (!$create) {
                $row = $this->structure->lockGroup((int)$row['ID'])->fetch();
                if (false === $row) {
                    throw new HttpException('NOT_FOUND', 404);
                }
                $old = $this->access->assignments([(int)$row['ID']])[(int)$row['ID']] ?? $old;
            }
            $desired = new GroupAssignmentOutputDto($input->teacherProvided ? $input->teacherId : $old->teacherId);
            $users = [];
            foreach ([$old->teacherId, $desired->teacherId] as $user) {
                if (null !== $user) {
                    $users[] = $user;
                }
            }
            $this->access->lockParticipants($actor, $bearer, $users);
            $operation = $create ? 'POST /shoots/' . $target->value . '/groups' : 'PATCH /groups/' . $target->value;
            $hash = $input->payloadHash();
            $previous = $this->operations->find($actor, $operation, $input->key)->fetch();
            if (false !== $previous) {
                if (!hash_equals((string)$previous['payload_hash'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }
                /** @var array{id:string,revision:int,assignmentSignature:string} $result */
                $result = json_decode((string)$previous['result_json'], true, 512, JSON_THROW_ON_ERROR);

                return new GroupMutationOutputDto($result['id'], $result['revision'], $result['assignmentSignature']);
            }
            $name = new StructureName($input->name ?? (string)$row['UF_NAME']);
            $kind = $create ? $input->groupKind : (string)$row['UF_KIND'];
            $id = $create ? new StructureId(Uuid::uuid4()->toString()) : $target;
            $nativeId = $create ? $this->structure->createGroup($id, $shootId, $name, $kind ?? '') : (int)$row['ID'];
            $revision = $create ? 1 : $this->structure->updateGroup($nativeId, $name, $input->revision ?? 0);
            $operationId = Uuid::uuid4()->toString();
            $signature = $this->access->replace(
                groupId: $nativeId,
                desired: $desired,
                expectedSignature: $input->assignmentSignature,
                replaceOccupied: $input->replaceAssignments,
                actorUserId: $actor,
                operationId: $operationId,
                reason: $input->reason,
            );
            $this->operations->record(
                id: $nativeId,
                from: $revision - 1,
                to: $revision,
                actor: $actor,
                operationId: $operationId,
                delta: json_encode([
                    'before' => $create ? null : ['name' => $row['UF_NAME'], 'teacherId' => $old->teacherId],
                    'after' => ['shootId' => $shootId, 'name' => $name->value, 'groupKind' => $kind, 'teacherId' => $desired->teacherId],
                    'reason' => $input->reason,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                aggregateType: 'group',
            );
            $result = new GroupMutationOutputDto($id->value, $revision, $signature);
            $this->operations->save($actor, $operation, $input->key, $hash, json_encode($result, JSON_THROW_ON_ERROR));

            return $result;
        });
    }
}
