<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 262144)]
#[StrictRequest]
final readonly class SaveGlobalConditionsRequestDto implements RequestDtoInterface
{
    /** @param list<ConditionProductRequestDto> $products */
    public function __construct(
        public int $revision,
        public int $catalogRevision,
        /** @var ConditionProductRequestDto[] */
        public array $products,
        public bool $giftEnabled,
        public int $giftThreshold,
        public bool $giftForStaff,
        public PaymentCostsRequestDto $paymentCosts,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
        #[RequestHeader('Authorization')]
        public string $authorization,
    ) {}
}
