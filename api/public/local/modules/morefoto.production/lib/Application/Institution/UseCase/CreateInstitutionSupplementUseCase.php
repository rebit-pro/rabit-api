<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Institution\Dto\InstitutionVersionOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveView;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Rebit\Share\Shared\Exception\HttpException;

/** Создаёт дополнение учреждения только с новыми строками заданий L1; повтор команды возвращает тот же выпуск. */
final readonly class CreateInstitutionSupplementUseCase
{
    public function __construct(private InstitutionArchiveAccess $access, private InstitutionSnapshot $snapshot, private InstitutionArchiveView $view) {}

    public function execute(int $actorId, string $institutionId, string $idempotencyKey): InstitutionVersionOutputDto
    {
        $institution = $this->access->institution($actorId, $institutionId, true);
        $archive = $this->access->command(
            $actorId,
            $idempotencyKey,
            'supplement|' . $institutionId,
            fn(InstitutionArchiveRequest $request): InstitutionArchive => $this->snapshot->capture($institution, true, $actorId, $request) ?? throw new HttpException('NOTHING_TO_BUILD', 409),
        );

        return $this->view->one($archive);
    }
}
