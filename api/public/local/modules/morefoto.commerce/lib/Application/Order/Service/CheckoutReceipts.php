<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Contract\CheckoutKeySealInterface;
use Morefoto\Commerce\Application\Order\Dto\CreatedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\PlacedOrderOutputDto;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderCheckoutRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

/** Делает оформление идемпотентным: резервирует Idempotency-Key до проверок, отличает другое тело и возвращает исходный заказ.
 * Копия личного ключа хранится только зашифрованной ключом повтора клиента, поэтому одна БД ключ не раскрывает.
 */
final readonly class CheckoutReceipts
{
    public function __construct(
        private OrderCheckoutRepository $receipts,
        private CheckoutRequestHash $hashes,
        private CheckoutKeySealInterface $seal,
        private OrderReader $reader,
        private OrderAccessKeyRepository $keys,
        private OrderCalendarPolicy $calendar,
    ) {}

    /** Возвращает исходный ответ для повтора либо null, если ключ повтора впервые зарезервирован этой транзакцией. */
    public function reserve(string $galleryToken, IdempotencyKey $key, CreateOrderInputDto $input): ?CreatedOrderOutputDto
    {
        $galleryHash = hash('sha256', $galleryToken);
        $requestHash = $this->hashes->hash($input);
        $row = $this->receipts->reserve($galleryHash, $this->keyHash($key), $requestHash);
        if (!hash_equals($row['REQUEST_HASH'], $requestHash)) {
            throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
        }
        if (null === $row['ORDER_ID']) {
            return null;
        }
        $orderId = (int)$row['ORDER_ID'];
        $accessKey = $this->seal->open((string)$row['SEALED_KEY'], $key->value, $this->context($galleryHash, $orderId));
        $expiresAt = $this->keys->expiresAt($orderId, hash('sha256', $accessKey));
        if (null === $expiresAt) {
            throw new OrderStorageException('Replayed order key is missing.');
        }

        return new CreatedOrderOutputDto(
            $this->reader->read($orderId),
            $accessKey,
            $this->calendar->display(new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'))),
        );
    }

    public function complete(string $galleryToken, IdempotencyKey $key, PlacedOrderOutputDto $placed): void
    {
        $galleryHash = hash('sha256', $galleryToken);
        $this->receipts->complete(
            $galleryHash,
            $this->keyHash($key),
            $placed->orderId,
            $this->seal->seal($placed->created->accessKey, $key->value, $this->context($galleryHash, $placed->orderId)),
        );
    }

    private function keyHash(IdempotencyKey $key): string
    {
        return hash('sha256', 'morefoto.order.checkout|' . $key->value);
    }

    private function context(string $galleryHash, int $orderId): string
    {
        return $galleryHash . '|' . $orderId;
    }
}
