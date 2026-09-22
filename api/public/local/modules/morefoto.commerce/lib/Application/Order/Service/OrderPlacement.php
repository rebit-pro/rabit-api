<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Contract\OrderTokenGeneratorInterface;
use Morefoto\Commerce\Application\Order\Dto\CreatedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\PlacedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRecordMapper;
use Morefoto\Commerce\Application\Storefront\Dto\ValidatedQuoteOutputDto;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderBuyer;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderNumber;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

/** Создаёт заказ из проверенного расчёта внутри транзакции оформления: снимок, номер MF и первый личный ключ.
 * Учреждение и съёмка берутся из контрактов Organization по группе ссылки, а не из запроса покупателя.
 */
final readonly class OrderPlacement
{
    public function __construct(
        private GroupReferenceInterface $groups,
        private MediaScopeInterface $scopes,
        private OrderRepository $orders,
        private OrderRecordMapper $records,
        private OrderAccessKeys $keys,
        private OrderReader $reader,
        private OrderTokenGeneratorInterface $tokens,
        private ClockInterface $clock,
    ) {}

    public function place(ValidatedQuoteOutputDto $validated, OrderBuyer $buyer, string $galleryToken, string $quoteToken): PlacedOrderOutputDto
    {
        $group = $validated->gallery->group;
        $scope = $this->scopes->resolve($this->groups->get($group->publicId)->shootId, $group->publicId);
        $lineIds = [];
        foreach ($validated->quote->quote['lines'] as $_) {
            $lineIds[] = $this->tokens->uuid();
        }
        $now = $this->clock->now();
        $records = $this->records->records($validated, $buyer, $scope, $this->tokens->uuid(), $lineIds, hash('sha256', $galleryToken), hash('sha256', $quoteToken), $now);
        $orderId = $this->orders->insert($records['order'], $records['lines']);
        $this->orders->assignNumber($orderId, (new OrderNumber($orderId))->value);
        $key = $this->keys->issue($orderId, $now, 'checkout');

        return new PlacedOrderOutputDto($orderId, new CreatedOrderOutputDto($this->reader->read($orderId), $key->key, $key->expiresAt));
    }
}
