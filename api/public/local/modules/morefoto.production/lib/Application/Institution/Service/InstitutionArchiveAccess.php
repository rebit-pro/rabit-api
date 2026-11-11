<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Service;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Domain\Archive\Exception\ArchiveLayoutException;
use Morefoto\Production\Domain\Institution\Exception\InstitutionArchiveBusyException;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Разрешает объектный доступ L1 и повтор команд учреждения: куратор читает только своё учреждение, управляет организатор.
 * Повтор с тем же ключом возвращает исходный выпуск; ключ другого действия отклоняется до нового эффекта.
 */
final readonly class InstitutionArchiveAccess
{
    public function __construct(
        private ProductionAccessInterface $access,
        private PrintInstitutionDirectoryInterface $institutions,
        private InstitutionArchiveRepositoryInterface $archives,
    ) {}

    public function institution(int $actorId, string $id, bool $manage = false): PrintInstitutionOutputDto
    {
        $scope = $this->access->scope($actorId);
        if ($manage && !$scope->canManage) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $institution = $this->institutions->find($id);
        if (null === $institution || (null !== $scope->institutionIds && !in_array($institution->nativeId, $scope->institutionIds, true))) {
            throw new HttpException('INSTITUTION_NOT_FOUND', 404);
        }

        return $institution;
    }

    public function archive(int $actorId, string $id, bool $manage = false): InstitutionArchive
    {
        $this->access->scope($actorId);
        $archive = $this->archives->find($id) ?? throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        $institution = $this->institution($actorId, $archive->institutionPublicId, $manage);
        if ($institution->nativeId !== $archive->institutionId) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }

        return $archive;
    }

    public function canManage(int $actorId): bool
    {
        return $this->access->scope($actorId)->canManage;
    }

    /** @param \Closure(InstitutionArchiveRequest): InstitutionArchive $operation */
    public function command(int $actorId, string $key, string $request, \Closure $operation): InstitutionArchive
    {
        if (1 !== preg_match('/^[A-Za-z0-9._-]{16,128}$/D', $key)) {
            throw new HttpException('IDEMPOTENCY_KEY_REQUIRED', 422);
        }
        $hash = hash('sha256', $key);
        $signature = hash('sha256', $request);
        try {
            return $this->archives->exclusive('request:' . $actorId . ':' . $hash, function() use ($actorId, $hash, $signature, $operation): InstitutionArchive {
                $previous = $this->archives->request($actorId, $hash);
                if (null !== $previous) {
                    if ($signature !== $previous['request']) {
                        throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                    }

                    return $this->archive($actorId, $previous['archiveId'], true);
                }

                return $operation(new InstitutionArchiveRequest(actorId: $actorId, keyHash: $hash, requestHash: $signature));
            });
        } catch (InstitutionArchiveBusyException $error) {
            throw new HttpException('OPERATION_IN_PROGRESS', 409, $error);
        } catch (ArchiveLayoutException $error) {
            throw new HttpException($error->getMessage(), 409, $error);
        }
    }
}
