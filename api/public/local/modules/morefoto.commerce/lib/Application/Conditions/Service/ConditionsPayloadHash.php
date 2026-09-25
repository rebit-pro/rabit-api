<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;

/**
 * Считает отпечаток запроса сохранения условий для идемпотентного повтора: тот же ключ с тем же телом
 * возвращает прежний результат, с другим телом — конфликт. Политика расходов входит только в общие условия.
 */
final readonly class ConditionsPayloadHash
{
    public function create(SaveConditionsInputDto $input): string
    {
        $products = [];
        foreach ($input->products as $product) {
            $products[] = [$product->id, $product->price, $product->active, $product->staffDiscount];
        }
        usort($products, static fn(array $left, array $right): int => $left[0] <=> $right[0]);
        $payload = [
            'revision' => $input->revision,
            'catalogRevision' => $input->catalogRevision,
            'conditionsRevision' => $input->conditionsRevision,
            'inherit' => $input->inherit,
            'products' => $products,
            'giftEnabled' => $input->giftEnabled,
            'giftThreshold' => $input->giftThreshold,
            'giftForStaff' => $input->giftForStaff,
        ];
        // Group requests keep their previous hash shape, so their stored idempotency keys stay replayable.
        if (null !== $input->paymentCosts) {
            $payload['paymentCosts'] = [$input->paymentCosts->enabled, $input->paymentCosts->rateBps];
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
