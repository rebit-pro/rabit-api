<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationOutputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Morefoto\Organization\Domain\Structure\ValueObject\ShootDate;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Ramsey\Uuid\Uuid;

final readonly class SaveShootUseCase
{
    public function __construct(private StructureRepository $structure, private InstitutionOperationRepository $operations, private InstitutionAccessInterface $access, private InstitutionTransactionInterface $transaction) {}

    /** On create target is an institution UUID; on update it is a shoot UUID. */
    public function execute(int $actor, string $bearer, StructureId $target, bool $create, ShootMutationInputDto $input): ShootMutationOutputDto
    {
        if (($create && (null === $input->name || null !== $input->revision)) || (!$create && (null === $input->revision || (null === $input->name && !$input->dateProvided)))) {
            throw new HttpException('INVALID_MUTATION', 422);
        }

        return $this->transaction->execute(function() use ($actor, $bearer, $target, $create, $input): ShootMutationOutputDto {
            $this->access->lockState();
            if ('organizer' !== $this->access->scope($actor)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $row = $create ? $this->structure->institution($target)->fetch() : $this->structure->shoot($target)->fetch();
            if (false === $row) {
                throw new HttpException('NOT_FOUND', 404);
            }
            $institutionId = $create ? (int)$row['ID'] : (int)$row['UF_INSTITUTION_ID'];
            if (false === $this->structure->lockInstitution($institutionId)->fetch()) {
                throw new HttpException('NOT_FOUND', 404);
            }
            if (!$create) {
                $row = $this->structure->lockShoot((int)$row['ID'])->fetch();
                if (false === $row) {
                    throw new HttpException('NOT_FOUND', 404);
                }
            }
            $this->access->lockParticipants($actor, $bearer, []);
            $operation = $create ? 'POST /institutions/' . $target->value . '/shoots' : 'PATCH /shoots/' . $target->value;
            $hash = $input->payloadHash();
            $previous = $this->operations->find($actor, $operation, $input->key)->fetch();
            if (false !== $previous) {
                if (!hash_equals((string)$previous['payload_hash'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }
                /** @var array{id:string,revision:int} $result */
                $result = json_decode((string)$previous['result_json'], true, 512, JSON_THROW_ON_ERROR);

                return new ShootMutationOutputDto($result['id'], $result['revision']);
            }
            $name = new StructureName($input->name ?? (string)$row['UF_NAME']);
            $date = new ShootDate($input->dateProvided ? $input->date : ($create ? null : $row['UF_DATE']));
            $id = $create ? new StructureId(Uuid::uuid4()->toString()) : $target;
            $nativeId = $create ? $this->structure->createShoot($id, $institutionId, $name, $date) : (int)$row['ID'];
            $revision = $create ? 1 : $this->structure->updateShoot($nativeId, $name, $date, $input->revision ?? 0);
            $this->operations->record(
                id: $nativeId,
                from: $revision - 1,
                to: $revision,
                actor: $actor,
                operationId: Uuid::uuid4()->toString(),
                delta: json_encode([
                    'before' => $create ? null : ['name' => $row['UF_NAME'], 'date' => $row['UF_DATE']],
                    'after' => ['institutionId' => $institutionId, 'name' => $name->value, 'date' => $date->value],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                aggregateType: 'shoot',
            );
            $result = new ShootMutationOutputDto($id->value, $revision);
            $this->operations->save($actor, $operation, $input->key, $hash, json_encode($result, JSON_THROW_ON_ERROR));

            return $result;
        });
    }
}
