<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit\Support;

use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderEntitledPhotoOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEntitlementOutputDto;
use Rebit\Share\Contracts\Commerce\OrderEntitlementInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Заказ 7 с ключом KEY: кадр P1 куплен отдельно, ребёнок 12 — комплектом (P2, P3 и повтор P1). */
final class FilesFixture
{
    public const string KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public const string ORDER = '7d0c1a52-8d44-4f0e-9a52-2b8f5f1f0a00';
    public const string P1 = '11111111-1111-4111-8111-111111111111';
    public const string P2 = '22222222-2222-4222-8222-222222222222';
    public const string P3 = '33333333-3333-4333-8333-333333333333';
    public const string NOW = '2027-02-10 09:00:00';

    public string $paymentStatus = 'paid';
    public bool $latePayment = false;
    public ?string $until = '2027-02-28 07:00:00';
    public ?string $now = null;

    /** @var array<string, string> ID кадра → абсолютный путь оригинала */
    public array $paths = [];

    public function clock(): ClockInterface
    {
        $fixture = $this;

        return new class($fixture) implements ClockInterface {
            public function __construct(private readonly FilesFixture $fixture) {}

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->fixture->now ?? FilesFixture::NOW, new \DateTimeZone('UTC'));
            }
        };
    }

    public function access(): OrderFileAccess
    {
        return new OrderFileAccess($this->orders(), $this->childPhotos(), $this->originals(), new FileAccessPolicy(), $this->clock());
    }

    public function orders(): OrderEntitlementInterface
    {
        $fixture = $this;

        return new class($fixture) implements OrderEntitlementInterface {
            public function __construct(private readonly FilesFixture $fixture) {}

            public function byKey(?string $orderKey): OrderEntitlementOutputDto
            {
                if (FilesFixture::KEY !== $orderKey) {
                    throw new HttpException('ORDER_NOT_FOUND', 404);
                }

                return $this->byId(7);
            }

            public function byId(int $orderId): OrderEntitlementOutputDto
            {
                if (7 !== $orderId) {
                    throw new HttpException('ORDER_NOT_FOUND', 404);
                }
                $paid = 'paid' === $this->fixture->paymentStatus;

                return new OrderEntitlementOutputDto(
                    7,
                    FilesFixture::ORDER,
                    'MF-0007',
                    4,
                    $this->fixture->paymentStatus,
                    $paid ? '2027-01-31 07:00:00' : null,
                    $this->fixture->latePayment,
                    $paid ? $this->fixture->until : null,
                    [new OrderEntitledPhotoOutputDto(FilesFixture::P1, 'AB', 'AB001')],
                    [12],
                );
            }
        };
    }

    public function childPhotos(): ChildPhotosInterface
    {
        return new class implements ChildPhotosInterface {
            public function ready(int $shootId, array $childIds): array
            {
                $photo = static fn(string $id, string $code): GalleryAssignmentOutputDto => new GalleryAssignmentOutputDto('a-' . $code, $id, 'c-12', 12, 'CD', $code, 100, 100, 1);

                return 4 === $shootId && [12] === $childIds ? [$photo(FilesFixture::P2, 'CD001'), $photo(FilesFixture::P3, 'CD002'), $photo(FilesFixture::P1, 'CD003')] : [];
            }
        };
    }

    public function originals(): OriginalFilesInterface
    {
        $fixture = $this;

        return new class($fixture) implements OriginalFilesInterface {
            public function __construct(private readonly FilesFixture $fixture) {}

            public function originals(array $photoIds): array
            {
                $originals = [];
                foreach ($photoIds as $id) {
                    if (isset($this->fixture->paths[$id])) {
                        $path = $this->fixture->paths[$id];
                        $originals[$id] = new OriginalFileOutputDto($id, 'image/jpeg', is_file($path) ? (int)filesize($path) : 1000, 'shoot/' . substr($id, 0, 2) . '/' . $id . '.jpg', $path);
                    }
                }

                return $originals;
            }
        };
    }

    public function withOriginals(string ...$photoIds): self
    {
        foreach ($photoIds as $id) {
            $this->paths[$id] = '/nonexistent/' . $id . '.jpg';
        }

        return $this;
    }
}
