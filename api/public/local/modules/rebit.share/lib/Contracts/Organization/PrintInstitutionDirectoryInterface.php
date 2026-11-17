<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;

/** Учреждения для автоматической выгрузки печати; права сотрудника проверяет потребитель. */
interface PrintInstitutionDirectoryInterface
{
    public function find(string $id): ?PrintInstitutionOutputDto;

    /** @return list<PrintInstitutionOutputDto> по возрастанию nativeId, строго после переданного ID */
    public function after(int $nativeId, int $limit): array;
}
