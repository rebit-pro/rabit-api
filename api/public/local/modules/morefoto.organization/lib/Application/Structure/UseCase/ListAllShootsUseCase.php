<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\UseCase;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Structure\Dto\ShootListInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootListItemOutputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootListOutputDto;
use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отдаёт сотруднику страницу всех съёмок доступных ему учреждений для раздела «Съёмки» кабинета: организатор видит
 * все учреждения, куратор и заведующая — назначенные. Поиск по началу названия и сортировка применяются ко всему списку.
 */
final readonly class ListAllShootsUseCase
{
    public function __construct(
        private StructureRepository $structure,
        private InstitutionAccessInterface $access,
        private TokenResolverInterface $tokens,
        private CalendarClockInterface $clock,
    ) {}

    public function execute(int $actor, string $bearer, ShootListInputDto $input): ShootListOutputDto
    {
        $scope = $this->access->scope($actor);
        if (!in_array($scope->role, ['organizer', 'curator', 'head'], true)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $signature = $this->access->signature();
        $result = $this->structure->shootList(
            'organizer' === $scope->role ? null : $scope->institutionIds,
            $input->query,
            $input->sort,
            $input->descending,
            $input->pageSize,
            ($input->page - 1) * $input->pageSize,
            GroupStateSql::utc($this->clock->now()),
        );
        $items = [];
        $total = 0;
        while (false !== ($row = $result->fetch())) {
            $total = (int)$row['TOTAL'];
            if (null !== $row['ID']) {
                $items[] = new ShootListItemOutputDto(
                    id: (string)$row['UF_PUBLIC_ID'],
                    institutionId: (string)$row['INSTITUTION_PUBLIC_ID'],
                    institutionName: (string)$row['INSTITUTION_NAME'],
                    name: (string)$row['UF_NAME'],
                    date: null === $row['UF_DATE'] ? null : (string)$row['UF_DATE'],
                    revision: (int)$row['UF_REVISION'],
                    groupCount: (int)$row['GROUP_COUNT'],
                    openGroupCount: (int)$row['OPEN_COUNT'],
                );
            }
        }
        // The page is answered only if the session and the access read before the query still hold.
        $current = $this->access->scope($actor);
        if ($actor !== $this->tokens->resolveUserId($bearer)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        if ($scope->accessRevision !== $current->accessRevision || $scope->role !== $current->role || $signature !== $this->access->signature()) {
            throw new HttpException('ACCESS_CHANGED', 409);
        }

        return new ShootListOutputDto($items, $input->page, $input->pageSize, $total, (int)ceil($total / $input->pageSize));
    }
}
