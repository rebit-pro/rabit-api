<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Service;

use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;

/**
 * Предоставляет название и идентичность учреждения для печатной выгрузки без раскрытия собственного хранения.
 * Последовательный обход включает исторические учреждения и позволяет планировщику продолжать с сохранённого ID.
 */
final readonly class PrintInstitutionDirectory implements PrintInstitutionDirectoryInterface
{
    public function __construct(private InstitutionRepository $institutions) {}

    public function find(string $id): ?PrintInstitutionOutputDto
    {
        $row = $this->institutions->visible(new InstitutionId($id), null)->fetch();

        return false === $row ? null : $this->item($row);
    }

    public function after(int $nativeId, int $limit): array
    {
        $result = $this->institutions->after($nativeId, $limit);
        $items = [];
        while (false !== ($row = $result->fetch())) {
            $items[] = $this->item($row);
        }

        return $items;
    }

    /** @param array<string, mixed> $row */
    private function item(array $row): PrintInstitutionOutputDto
    {
        return new PrintInstitutionOutputDto((int)$row['ID'], (string)$row['UF_PUBLIC_ID'], (string)$row['UF_NAME']);
    }
}
