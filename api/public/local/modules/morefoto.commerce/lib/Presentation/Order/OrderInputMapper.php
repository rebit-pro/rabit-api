<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order;

use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerInputDto;
use Morefoto\Commerce\Application\Order\Dto\SearchOrdersInputDto;
use Morefoto\Commerce\Domain\Order\Enum\PaymentStatusEnum;
use Morefoto\Commerce\Domain\Order\Enum\ProductionStatusEnum;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Presentation\Order\Dto\CreateOrderRequestDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderListRequestDto;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use Rebit\Share\Presentation\Consent\AcceptedDocumentInputMapper;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class OrderInputMapper
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';

    public function __construct(private StorefrontMapper $storefront, private AcceptedDocumentInputMapper $consents) {}

    public function key(CreateOrderRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }

    public function create(CreateOrderRequestDto $request): CreateOrderInputDto
    {
        $buyer = $request->buyer;

        return new CreateOrderInputDto(
            $request->quoteToken,
            $this->storefront->lines($request->lines),
            new OrderBuyerInputDto($buyer->name, $buyer->phone, $buyer->email, $buyer->comment, $buyer->receiptChannel, $buyer->reviewed),
            $this->consents->documents($request->consents),
        );
    }

    public function search(StaffOrderListRequestDto $request): SearchOrdersInputDto
    {
        // Settlement filters wait for their owner (I1–I3); late payment exists since G1.
        if (null !== $this->optional($request->settlement)) {
            throw new HttpException('FILTER_UNAVAILABLE', 422);
        }
        $late = $this->optional($request->late);
        if (null !== $late && !in_array($late, ['true', 'false'], true)) {
            throw new HttpException('INVALID_FILTER', 422);
        }
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }
        $query = $this->optional($request->q);
        if (null !== $query && (2 > mb_strlen($query) || 100 < mb_strlen($query))) {
            throw new HttpException('INVALID_FILTER', 422);
        }
        $paymentStatus = $this->optional($request->paymentStatus);
        $productionStatus = $this->optional($request->productionStatus);
        if ((null !== $paymentStatus && null === PaymentStatusEnum::tryFrom($paymentStatus))
            || (null !== $productionStatus && null === ProductionStatusEnum::tryFrom($productionStatus))) {
            throw new HttpException('INVALID_FILTER', 422);
        }
        $dateFrom = $this->date($request->dateFrom);
        $dateTo = $this->date($request->dateTo);
        if (null !== $dateFrom && null !== $dateTo && $dateFrom > $dateTo) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return new SearchOrdersInputDto(
            query: $query,
            institutionId: $this->uuid($request->institutionId),
            shootId: $this->uuid($request->shootId),
            groupId: $this->uuid($request->groupId),
            paymentStatus: $paymentStatus,
            productionStatus: $productionStatus,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            page: $request->page,
            pageSize: $request->pageSize,
            latePayment: null === $late ? null : 'true' === $late,
        );
    }

    private function optional(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    private function uuid(?string $value): ?string
    {
        $value = $this->optional($value);
        if (null !== $value && 1 !== preg_match(self::UUID, $value)) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return $value;
    }

    private function date(?string $value): ?string
    {
        $value = $this->optional($value);
        if (null === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return $value;
    }
}
