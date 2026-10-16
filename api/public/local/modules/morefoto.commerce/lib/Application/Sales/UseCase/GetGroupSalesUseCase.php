<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Morefoto\Commerce\Application\Sales\Dto\GroupSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesScopeOutputDto;
use Morefoto\Commerce\Domain\Sales\Repository\GroupSalesRepository;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * COM-14: показывает воспитателю и ответственным, сколько продано в группе по оплаченным заказам — печать по форматам,
 * цифровые файлы, заказы, купившие семьи и сумму оплат. Группа вне области сотрудника отвечает как несуществующая.
 */
final readonly class GetGroupSalesUseCase
{
    public function __construct(
        private SalesAccessInterface $access,
        private GroupDirectoryInterface $groups,
        private GroupSalesRepository $sales,
    ) {}

    public function execute(int $actorId, string $groupId): GroupSalesOutputDto
    {
        $scope = $this->access->scope($actorId);
        $group = $this->groups->find($groupId);
        if (null === $group || !$this->visible($scope, $group)) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }
        $totals = $this->sales->totals($group->id);
        $products = [];
        $result = $this->sales->printsByProduct($group->id);
        while (false !== ($row = $result->fetch())) {
            $products[] = new ProductSalesOutputDto((string)$row['PRODUCT_PUBLIC_ID'], (string)$row['NAME'], (int)$row['PRINTS']);
        }

        return new GroupSalesOutputDto(
            groupId: $group->id,
            paidOrders: $totals['ORDERS'],
            families: $totals['FAMILIES'],
            prints: $totals['PRINTS'],
            digitalFiles: $totals['DIGITAL'],
            bundles: $totals['BUNDLES'],
            paidTotal: $totals['PAID_TOTAL'],
            products: $products,
        );
    }

    private function visible(SalesScopeOutputDto $scope, GroupDirectoryItemOutputDto $group): bool
    {
        return (null === $scope->institutionIds || in_array($group->institutionNativeId, $scope->institutionIds, true))
            && (null === $scope->groupIds || in_array($group->nativeId, $scope->groupIds, true));
    }
}
