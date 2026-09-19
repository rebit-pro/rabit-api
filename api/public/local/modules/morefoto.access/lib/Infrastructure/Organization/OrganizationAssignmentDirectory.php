<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Organization;

use Bitrix\Main\Application;
use Morefoto\Access\Application\Staff\Contract\AssignmentDirectoryInterface;
use Morefoto\Access\Application\Staff\Dto\AssignmentDirectoryOutputDto;
use Morefoto\Access\Application\Staff\Dto\AssignmentGroupOutputDto;
use Morefoto\Access\Application\Staff\Dto\AssignmentInstitutionOutputDto;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class OrganizationAssignmentDirectory implements AssignmentDirectoryInterface
{
    public function snapshot(bool $lock = false): AssignmentDirectoryOutputDto
    {
        try {
            $connection = Application::getConnection();
            $suffix = $lock ? ' FOR UPDATE' : '';
            $institutions = [];
            $result = $connection->query(
                'SELECT ID,UF_PUBLIC_ID,UF_NAME,UF_ADDRESS FROM b_hlbd_mf_institution ORDER BY UF_NAME,ID' . $suffix,
            );
            while (false !== ($row = $result->fetch())) {
                $institutions[] = new AssignmentInstitutionOutputDto(
                    internalId: (int)$row['ID'],
                    id: (string)$row['UF_PUBLIC_ID'],
                    name: (string)$row['UF_NAME'],
                    address: (string)$row['UF_ADDRESS'],
                );
            }
            $groups = [];
            $result = $connection->query(
                'SELECT g.ID,g.UF_PUBLIC_ID,g.UF_NAME,s.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID,s.UF_NAME AS SHOOT_NAME,'
                . 'i.UF_PUBLIC_ID AS INSTITUTION_PUBLIC_ID,i.UF_NAME AS INSTITUTION_NAME '
                . 'FROM b_hlbd_mf_group g JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID '
                . 'JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID '
                . 'ORDER BY i.UF_NAME,s.UF_NAME,g.UF_NAME,g.ID' . $suffix,
            );
            while (false !== ($row = $result->fetch())) {
                $groups[] = new AssignmentGroupOutputDto(
                    internalId: (int)$row['ID'],
                    id: (string)$row['UF_PUBLIC_ID'],
                    name: (string)$row['UF_NAME'],
                    shootId: (string)$row['SHOOT_PUBLIC_ID'],
                    shootName: (string)$row['SHOOT_NAME'],
                    institutionId: (string)$row['INSTITUTION_PUBLIC_ID'],
                    institutionName: (string)$row['INSTITUTION_NAME'],
                );
            }

            return new AssignmentDirectoryOutputDto($institutions, $groups);
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read Organization assignment directory.', 0, $exception);
        }
    }
}
