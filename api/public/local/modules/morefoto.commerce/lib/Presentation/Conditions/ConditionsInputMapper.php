<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions;

use Morefoto\Commerce\Application\Conditions\Dto\PaymentCostsInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ProductConditionInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\ConditionProductRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGlobalConditionsRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGroupConditionsRequestDto;

/** Переводит типизированные запросы условий продажи во входные DTO сценария без собственных проверок. */
final readonly class ConditionsInputMapper
{
    public const string GROUP_ID_PATTERN = '/^[0-9A-Fa-f-]{36}$/D';

    /** Bearer-токен нужен сценарию для повторной проверки сессии под блокировкой профиля. */
    public function token(string $authorization): string
    {
        return str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
    }

    public function saveGlobal(SaveGlobalConditionsRequestDto $request): SaveConditionsInputDto
    {
        return new SaveConditionsInputDto(
            revision: $request->revision,
            catalogRevision: $request->catalogRevision,
            products: $this->products($request->products),
            giftEnabled: $request->giftEnabled,
            giftThreshold: $request->giftThreshold,
            giftForStaff: $request->giftForStaff,
            paymentCosts: new PaymentCostsInputDto($request->paymentCosts->enabled, $request->paymentCosts->rateBps),
        );
    }

    public function saveGroup(SaveGroupConditionsRequestDto $request): SaveConditionsInputDto
    {
        return new SaveConditionsInputDto(
            revision: $request->revision,
            catalogRevision: $request->catalogRevision,
            products: $this->products($request->products),
            giftEnabled: $request->giftEnabled,
            giftThreshold: $request->giftThreshold,
            giftForStaff: $request->giftForStaff,
            conditionsRevision: $request->conditionsRevision,
            inherit: $request->inherit,
        );
    }

    /**
     * @param list<ConditionProductRequestDto> $products
     *
     * @return list<ProductConditionInputDto>
     */
    private function products(array $products): array
    {
        $result = [];
        foreach ($products as $product) {
            $result[] = new ProductConditionInputDto($product->id, $product->price, $product->active, $product->staffDiscount);
        }

        return $result;
    }
}
