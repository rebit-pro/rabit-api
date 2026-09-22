<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Request\ValueObject;

final readonly class StaffTransferPlan
{
    /**
     * @param list<array{
     *     rowId: string,
     *     groupId: string,
     *     childCode: string,
     *     targetCode: string,
     *     hasOrders: bool,
     *     photos: list<array{id: string, code: string, revision: int}>,
     * }> $bundles по порядку строк заявки
     */
    public function __construct(
        public array $bundles,
        public bool $hasOrders,
        public string $signature,
    ) {}
}
