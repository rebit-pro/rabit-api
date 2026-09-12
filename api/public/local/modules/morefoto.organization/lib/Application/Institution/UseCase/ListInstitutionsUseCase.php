<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\UseCase;

use Morefoto\Organization\Application\Institution\Dto\InstitutionOutputDto;
use Morefoto\Organization\Application\Institution\Dto\InstitutionPageOutputDto;
use Morefoto\Organization\Application\Institution\Dto\ListInstitutionsInputDto;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;

final readonly class ListInstitutionsUseCase
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function execute(ListInstitutionsInputDto $input): InstitutionPageOutputDto
    {
        $result = $this->institutions->page($input->query, $input->pageSize, $input->offset());
        $items = [];
        $total = 0;
        /** @var array{
         *     UF_PUBLIC_ID: null|string,
         *     UF_NAME: null|string,
         *     UF_ADDRESS: null|string,
         *     UF_REVISION: null|int|string,
         *     TOTAL: int|string,
         * }|false $row */
        $row = $result->fetch();
        while (false !== $row) {
            $total = (int)$row['TOTAL'];
            if (null !== $row['UF_PUBLIC_ID']) {
                $items[] = InstitutionOutputDto::fromRow($row);
            }
            $row = $result->fetch();
        }

        return new InstitutionPageOutputDto($items, $input->page, $input->pageSize, $total);
    }
}
