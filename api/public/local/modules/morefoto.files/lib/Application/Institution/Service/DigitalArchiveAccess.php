<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Service;

use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет служебную роль и актуальную принадлежность учреждения для каждой операции и отдачи файла. */
final readonly class DigitalArchiveAccess
{
    public function __construct(
        private InstitutionAccessInterface $access,
        private PrintInstitutionDirectoryInterface $institutions,
        private DigitalArchiveRepositoryInterface $archives,
    ) {}

    public function institution(int $actorId, string $id, bool $manage = false): PrintInstitutionOutputDto
    {
        $scope = $this->access->scope($actorId);
        if (!in_array($scope->role, ['organizer', 'curator'], true) || ($manage && 'organizer' !== $scope->role)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $institution = $this->institutions->find($id);
        if (null === $institution || ('curator' === $scope->role && !in_array($institution->nativeId, $scope->institutionIds, true))) {
            throw new HttpException('INSTITUTION_NOT_FOUND', 404);
        }

        return $institution;
    }

    public function archive(int $actorId, string $id): DigitalArchive
    {
        $archive = $this->archives->find($id) ?? throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        $institution = $this->institution($actorId, $archive->institutionPublicId);
        if ($institution->nativeId !== $archive->institutionId) {
            throw new HttpException('ARCHIVE_NOT_FOUND', 404);
        }

        return $archive;
    }

    public function canCreate(int $actorId): bool
    {
        return 'organizer' === $this->access->scope($actorId)->role;
    }
}
