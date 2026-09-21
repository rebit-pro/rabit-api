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

    public function issue(string $groupId, string $hash): void
    {
        $this->execute("INSERT INTO mf_gallery_capability(TOKEN_HASH,GROUP_PUBLIC_ID,REVISION,REVOKED,CREATED_AT) VALUES('{$hash}','{$groupId}',1,0,UTC_TIMESTAMP())");
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
