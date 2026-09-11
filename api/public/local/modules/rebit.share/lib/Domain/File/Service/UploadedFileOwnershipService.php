<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Service;

use Bitrix\Main\Data\ManagedCache;

final readonly class UploadedFileOwnershipService
{
    private const string CACHE_KEY_PREFIX = 'rebit_share_upload_owner_';
    private const int CACHE_TTL = 3600;

    public function __construct(
        private ManagedCache $cache,
    ) {}

    public function remember(int $fileId, int $userId, string $moduleId): void
    {
        $this->cache->set(
            $this->buildCacheKey($fileId),
            [
                'userId' => $userId,
                'moduleId' => $moduleId,
            ],
        );
    }

    /**
     * @return null|array{
     *     userId: int,
     *     moduleId: string,
     * }
     */
    public function resolve(int $fileId): ?array
    {
        $cacheKey = $this->buildCacheKey($fileId);

        if (!$this->cache->read(self::CACHE_TTL, $cacheKey)) {
            return null;
        }

        $payload = $this->cache->get($cacheKey);

        if (!is_array($payload)) {
            return null;
        }

        $userId = $payload['userId'] ?? null;
        $moduleId = $payload['moduleId'] ?? null;

        if (!is_int($userId) || !is_string($moduleId) || '' === $moduleId) {
            return null;
        }

        return [
            'userId' => $userId,
            'moduleId' => $moduleId,
        ];
    }

    private function buildCacheKey(int $fileId): string
    {
        return self::CACHE_KEY_PREFIX . $fileId;
    }
}
