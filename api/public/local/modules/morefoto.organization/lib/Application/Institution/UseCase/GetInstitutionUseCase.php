<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Dto\InstitutionOutputDto;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionNotFoundException;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;

final readonly class GetInstitutionUseCase
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function execute(InstitutionId $id): InstitutionOutputDto
    {
        /** @var array{UF_PUBLIC_ID: string, UF_NAME: string, UF_ADDRESS: string, UF_REVISION: int|string}|false $row */
        $row = $this->institutions->find($id)->fetch();
        if (false === $row) {
            throw new InstitutionNotFoundException('Institution does not exist.');
        }

        return InstitutionOutputDto::fromRow($row);
    }
}
