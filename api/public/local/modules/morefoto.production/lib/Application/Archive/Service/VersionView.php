<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Dto\ProductOutputDto;
use Morefoto\Production\Application\Archive\Dto\VersionOutputDto;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;

/**
 * Показывает версии сотруднику: ссылка без входа выдаётся только готовой версии в срок хранения и всегда та же для
 * текущего поколения — повторное копирование не обрывает ссылку, уже отправленную лаборатории (L1-DEC-03).
 */
final readonly class VersionView
{
    public const string ARCHIVE_PATH = '/api/v1/public/production/archives/';

    public function __construct(
        private ArchiveRepositoryInterface $archives,
        private ArchiveLinkInterface $links,
    ) {}

    /**
     * @param list<ArchiveVersion> $versions
     *
     * @return list<VersionOutputDto>
     */
    public function many(array $versions, \DateTimeImmutable $now): array
    {
        if ([] === $versions) {
            return [];
        }
        $products = $this->archives->products(array_map(static fn(ArchiveVersion $version): int => $version->id, $versions));
        $views = [];
        foreach ($versions as $version) {
            $items = [];
            foreach ($products[$version->id] ?? [] as $product) {
                $items[] = new ProductOutputDto($product->name, $product->format, $product->files, $product->prints);
            }
            $available = VersionStatusEnum::READY === $version->status && null !== $version->expiresAt && $now < $version->expiresAt;
            $views[] = new VersionOutputDto(
                id: $version->publicId,
                groupId: $version->groupPublicId,
                number: $version->number,
                status: $version->status->value,
                lines: $version->linesCount,
                files: $version->filesCount,
                prints: $version->printsCount,
                waitingOrders: $version->waitingOrders,
                products: $items,
                manual: null !== $version->createdBy,
                createdAt: $version->createdAt,
                readyAt: $version->readyAt,
                expiresAt: $version->expiresAt,
                bytes: $version->bytes,
                errorCode: $version->errorCode,
                archiveUrl: $available ? self::ARCHIVE_PATH . $this->links->token($version->publicId, $version->linkGeneration) : null,
            );
        }

        return $views;
    }

    public function one(ArchiveVersion $version, \DateTimeImmutable $now): VersionOutputDto
    {
        return $this->many([$version], $now)[0];
    }
}
