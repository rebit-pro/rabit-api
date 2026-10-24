<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Service;

use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportAreaOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesScopeOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Domain\Sales\ValueObject\SalesArea;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Переводит запрошенные учреждение, съёмку или группу в уровень отчёта и его группы строго в области сотрудника:
 * самый узкий фильтр задаёт уровень, а объект вне области или с несогласованными предками отвечает как несуществующий.
 */
final readonly class SalesReportScope
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D';
    private const int PAGE_SIZE = 100;

    public function __construct(private SalesAccessInterface $access, private GroupDirectoryInterface $groups) {}

    public function resolve(int $actorId, SalesReportInputDto $input): SalesReportAreaOutputDto
    {
        $scope = $this->access->scope($actorId);
        $this->assertId($input->institutionId, 'INSTITUTION_NOT_FOUND');
        $this->assertId($input->shootId, 'SHOOT_NOT_FOUND');
        $this->assertId($input->groupId, 'GROUP_NOT_FOUND');
        $area = new SalesArea($scope->institutionIds, $scope->groupIds, $input->institutionId, $input->shootId, $input->groupId);
        if (null !== $input->groupId) {
            $group = $this->groups->find($input->groupId);
            if (null === $group || !$this->visible($scope, $group)
                || (null !== $input->shootId && $input->shootId !== $group->shootId)
                || (null !== $input->institutionId && $input->institutionId !== $group->institutionId)) {
                throw new HttpException('GROUP_NOT_FOUND', 404);
            }

            return new SalesReportAreaOutputDto(SalesReportLevelEnum::GROUP, $area, [$group]);
        }
        $groups = $this->all($scope, $input->institutionId, $input->shootId);
        [$level, $missing] = match (true) {
            null !== $input->shootId => [SalesReportLevelEnum::SHOOT, 'SHOOT_NOT_FOUND'],
            null !== $input->institutionId => [SalesReportLevelEnum::INSTITUTION, 'INSTITUTION_NOT_FOUND'],
            default => [SalesReportLevelEnum::ALL, null],
        };
        if (null !== $missing && [] === $groups) {
            throw new HttpException($missing, 404);
        }

        return new SalesReportAreaOutputDto($level, $area, $groups);
    }

    /** @return list<GroupDirectoryItemOutputDto> */
    private function all(SalesScopeOutputDto $scope, ?string $institutionId, ?string $shootId): array
    {
        $groups = [];
        $page = 1;
        do {
            $result = $this->groups->page(new GroupDirectoryQueryInputDto(
                institutionIds: $scope->institutionIds,
                groupIds: $scope->groupIds,
                institutionId: $institutionId,
                shootId: $shootId,
                state: null,
                page: $page,
                pageSize: self::PAGE_SIZE,
            ));
            array_push($groups, ...$result->items);
            ++$page;
        } while (self::PAGE_SIZE === count($result->items) && count($groups) < $result->total);

        return $groups;
    }

    private function visible(SalesScopeOutputDto $scope, GroupDirectoryItemOutputDto $group): bool
    {
        return (null === $scope->institutionIds || in_array($group->institutionNativeId, $scope->institutionIds, true))
            && (null === $scope->groupIds || in_array($group->nativeId, $scope->groupIds, true));
    }

    private function assertId(?string $id, string $code): void
    {
        if (null !== $id && 1 !== preg_match(self::UUID, $id)) {
            throw new HttpException($code, 404);
        }
    }
}
