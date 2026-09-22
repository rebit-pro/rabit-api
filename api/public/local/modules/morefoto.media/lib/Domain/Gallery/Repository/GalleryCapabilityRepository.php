<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Repository;

use Bitrix\Main\Application;
use Morefoto\Media\Domain\Gallery\Exception\GalleryStorageException;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class GalleryCapabilityRepository
{
    /** @return array{GROUP_PUBLIC_ID:string, REVISION:int|string}|false */
    public function find(string $hash): array|false
    {
        try {
            return Application::getConnection()->query("SELECT GROUP_PUBLIC_ID,REVISION FROM mf_gallery_capability WHERE TOKEN_HASH='{$hash}' AND REVOKED=0")->fetch();
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot resolve gallery capability.', 0, $error);
        }
    }

    /** The raw key is kept for staff by decision F2; buyers are still resolved by its hash only. */
    public function issue(string $groupId, string $hash, string $token): void
    {
        $this->execute("INSERT INTO mf_gallery_capability(TOKEN_HASH,TOKEN,GROUP_PUBLIC_ID,REVISION,REVOKED,CREATED_AT) VALUES('{$hash}','{$token}','{$groupId}',1,0,UTC_TIMESTAMP())");
    }

    /** @return array{TOKEN:string, CREATED_AT:string}|false newest active key that staff can copy */
    public function current(string $groupId): array|false
    {
        try {
            return Application::getConnection()->query("SELECT TOKEN,DATE_FORMAT(CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT FROM mf_gallery_capability
                WHERE GROUP_PUBLIC_ID='{$groupId}' AND REVOKED=0 AND TOKEN IS NOT NULL ORDER BY CREATED_AT DESC,TOKEN_HASH DESC LIMIT 1")->fetch();
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read gallery capability.', 0, $error);
        }
    }

    public function revoke(string $hash, int $revision): void
    {
        $this->execute("UPDATE mf_gallery_capability SET REVOKED=1,REVISION=REVISION+1 WHERE TOKEN_HASH='{$hash}' AND REVISION={$revision} AND REVOKED=0");
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot persist gallery capability.', 0, $error);
        }
    }
}
