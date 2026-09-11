<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Service;

use Bitrix\Main\Data\ManagedCache;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Repository\UploadedFileOwnerRepository;

final readonly class UploadedFileOwnershipService
{
    private const string CACHE_KEY_PREFIX = 'rebit_share_upload_owner_v2_';
    private const int CACHE_TTL = 3600;

    public function __construct(
        private UploadedFileOwnerRepository $repository,
        private ManagedCache $cache,
    ) {}

    public function remember(int $fileId, int $userId, string $moduleId): void
    {
        if (0 >= $fileId || 0 >= $userId || '' === $moduleId) {
            throw new FileUploadFailedException('Некорректные данные владельца файла.');
        }

        $this->repository->add($fileId, $userId, $moduleId);
        // A cache failure cannot invalidate a successful durable ownership write.
        try {
            $key = $this->buildCacheKey($fileId);
            $this->cache->clean($key);
            $this->cache->read(self::CACHE_TTL, $key);
            $this->cache->set($key, ['userId' => $userId, 'moduleId' => $moduleId]);
        } catch (\Throwable) {
            // The database remains authoritative; resolve() can rebuild this entry.
        }
    }

    /**
     * Legacy files without an ownership row are deliberately unresolved.
     *
     * @return null|array{userId: int, moduleId: string}
     */
    public function resolve(int $fileId): ?array
    {
        if (0 >= $fileId) {
            return null;
        }

        $key = $this->buildCacheKey($fileId);
        $cacheUsable = true;
        try {
            if ($this->cache->read(self::CACHE_TTL, $key)) {
                $cached = $this->cache->get($key);
                if (is_array($cached)
                    && is_int($cached['userId'] ?? null) && 0 < $cached['userId']
                    && is_string($cached['moduleId'] ?? null) && '' !== $cached['moduleId']) {
                    return ['userId' => $cached['userId'], 'moduleId' => $cached['moduleId']];
                }
                $this->cache->clean($key);
                $this->cache->read(self::CACHE_TTL, $key);
            }
        } catch (\Throwable) {
            $cacheUsable = false;
        }

        /** @var array{
         *     USER_ID: int|string,
         *     MODULE_ID: string,
         * }|false $row */
        $row = $this->repository->findByFileId($fileId)->fetch();
        if (false === $row) {
            return null;
        }
        $userId = (int)$row['USER_ID'];
        $moduleId = (string)$row['MODULE_ID'];
        if (0 >= $userId || '' === $moduleId) {
            return null;
        }

        $owner = ['userId' => $userId, 'moduleId' => $moduleId];
        if ($cacheUsable) {
            try {
                $this->cache->set($key, $owner);
            } catch (\Throwable) {
                // Cache availability never changes the durable authorization result.
            }
        }

        return $owner;
    }

    public function isOwnedBy(int $fileId, int $userId, string $moduleId): bool
    {
        if (0 >= $userId || '' === $moduleId) {
            return false;
        }

        $owner = $this->resolve($fileId);

        return null !== $owner && $userId === $owner['userId'] && $moduleId === $owner['moduleId'];
    }

    private function buildCacheKey(int $fileId): string
    {
        return self::CACHE_KEY_PREFIX . $fileId;
    }
}
