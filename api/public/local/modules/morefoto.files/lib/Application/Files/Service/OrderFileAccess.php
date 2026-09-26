<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Service;

use Morefoto\Files\Application\Files\Dto\EntitledFileOutputDto;
use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderEntitlementOutputDto;
use Rebit\Share\Contracts\Commerce\OrderEntitlementInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;

/** Вычисляет текущее право заказа на оригиналы: состояние по оплате и сроку D10 и точный список файлов, которые можно отдать сейчас.
 * Комплект и подарок раскрываются в текущие готовые кадры ребёнка (J1-DEC-01), поэтому каждая выдача пересчитывает право заново.
 */
final readonly class OrderFileAccess
{
    public function __construct(
        private OrderEntitlementInterface $orders,
        private ChildPhotosInterface $childPhotos,
        private OriginalFilesInterface $originals,
        private FileAccessPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function byKey(?string $orderKey): FileAccessOutputDto
    {
        return $this->resolve($this->orders->byKey($orderKey));
    }

    public function byOrderId(int $orderId): FileAccessOutputDto
    {
        return $this->resolve($this->orders->byId($orderId));
    }

    private function resolve(OrderEntitlementOutputDto $order): FileAccessOutputDto
    {
        $now = $this->clock->now();
        $until = null === $order->filesAvailableUntil ? null : new \DateTimeImmutable($order->filesAvailableUntil, new \DateTimeZone('UTC'));
        $state = $this->policy->state($order->paymentStatus, $order->latePayment, $until, $now, true);
        $files = FilesStateEnum::AVAILABLE === $state ? $this->files($order) : [];
        if (FilesStateEnum::AVAILABLE === $state && [] === $files) {
            $state = FilesStateEnum::EMPTY;
        }

        return new FileAccessOutputDto($order->id, $order->publicId, $order->number, $state, $until, $files);
    }

    /** @return array<string, EntitledFileOutputDto> */
    private function files(OrderEntitlementOutputDto $order): array
    {
        /** @var array<string, array{0: string, 1: string}> $candidates */
        $candidates = [];
        foreach ($order->photos as $photo) {
            $candidates[$photo->photoId] ??= [$photo->childCode, $photo->code];
        }
        if ([] !== $order->childIds) {
            foreach ($this->childPhotos->ready($order->shootId, $order->childIds) as $photo) {
                $candidates[$photo->photoId] ??= [$photo->childCode, $photo->code];
            }
        }
        if ([] === $candidates) {
            return [];
        }
        $originals = $this->originals->originals(array_map('strval', array_keys($candidates)));
        $files = [];
        $names = [];
        foreach ($candidates as $photoId => [$childCode, $code]) {
            $photoId = (string)$photoId;
            $original = $originals[$photoId] ?? null;
            if (null === $original) {
                continue;
            }
            $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $code) ?: substr($photoId, 0, 8);
            $extension = match ($original->mimeType) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $name = $base . '.' . $extension;
            for ($suffix = 2; isset($names[$name]); ++$suffix) {
                $name = $base . '-' . $suffix . '.' . $extension;
            }
            $names[$name] = true;
            $files[$photoId] = new EntitledFileOutputDto(
                photoId: $photoId,
                childCode: $childCode,
                code: $code,
                filename: $name,
                mimeType: $original->mimeType,
                bytes: $original->bytes,
                relativePath: $original->relativePath,
                absolutePath: $original->absolutePath,
            );
        }

        return $files;
    }
}
