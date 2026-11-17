<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Institution\Dto\InstitutionVersionOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveView;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Отзывает прежний доступ или восстанавливает файл той же версии учреждения без повторной фиксации печати. */
final readonly class RenewInstitutionArchiveUseCase
{
    public function __construct(
        private InstitutionArchiveAccess $access,
        private InstitutionArchiveRepositoryInterface $archives,
        private ArchiveStorageInterface $storage,
        private InstitutionArchiveView $view,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $archiveId, string $idempotencyKey): InstitutionVersionOutputDto
    {
        $archive = $this->access->archive($actorId, $archiveId, true);
        $result = $this->access->command(
            $actorId,
            $idempotencyKey,
            'renew|' . $archiveId,
            fn(InstitutionArchiveRequest $request): InstitutionArchive => $this->archives->exclusive('institution:' . $archive->institutionPublicId, function() use ($actorId, $archiveId, $request): InstitutionArchive {
                $fresh = $this->access->archive($actorId, $archiveId, true);
                if (in_array($fresh->status, [VersionStatusEnum::PENDING, VersionStatusEnum::EMPTY], true)) {
                    throw new HttpException('ARCHIVE_NOT_READY', 409);
                }
                $rebuild = VersionStatusEnum::READY !== $fresh->status || null === $fresh->expiresAt || $fresh->expiresAt <= $this->clock->now()
                    || null === $fresh->archivePath || !$this->storage->exists($fresh->archivePath);
                if (!$this->archives->renew($archiveId, $fresh->generation, $rebuild, $request)) {
                    throw new HttpException('VERSION_CONFLICT', 409);
                }

                return $this->access->archive($actorId, $archiveId, true);
            }),
        );

        return $this->view->one($result);
    }
}
