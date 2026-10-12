<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Repository;

use Bitrix\Main\Application;
use Morefoto\Media\Domain\Gallery\Exception\GalleryStorageException;
use Morefoto\Media\Domain\Gallery\ValueObject\GalleryAlias;
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

    /** @return array{TOKEN:string, TOKEN_HASH:string, ALIAS:null|string, CREATED_AT:string}|false newest active key that staff can copy */
    public function current(string $groupId): array|false
    {
        try {
            return Application::getConnection()->query("SELECT c.TOKEN,c.TOKEN_HASH,a.ALIAS,DATE_FORMAT(c.CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT
                FROM mf_gallery_capability c LEFT JOIN mf_gallery_alias a ON a.TOKEN_HASH=c.TOKEN_HASH
                WHERE c.GROUP_PUBLIC_ID='{$groupId}' AND c.REVOKED=0 AND c.TOKEN IS NOT NULL ORDER BY c.CREATED_AT DESC,c.TOKEN_HASH DESC LIMIT 1")->fetch();
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read gallery capability.', 0, $error);
        }
    }

    /**
     * F3: gives the key its single short alias; a repeat returns the same one, a clash with another key's alias is
     * retried with a fresh value. The key row and its CREATED_AT (the lower bound of T0) are not touched.
     */
    public function attachAlias(string $hash): string
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->execute("INSERT IGNORE INTO mf_gallery_alias(ALIAS,TOKEN_HASH,CREATED_AT) VALUES('" . GalleryAlias::random() . "','{$hash}',UTC_TIMESTAMP())");
            $row = $this->read("SELECT ALIAS FROM mf_gallery_alias WHERE TOKEN_HASH='{$hash}'");
            if (false !== $row) {
                return (string)$row['ALIAS'];
            }
        }
        throw new GalleryStorageException('Cannot give the gallery key a unique alias.');
    }

    /** Raw key of an active staff-visible key behind the alias; a revoked or unknown alias resolves to nothing. */
    public function tokenByAlias(string $alias): ?string
    {
        if (!GalleryAlias::matches($alias)) {
            return null;
        }
        $row = $this->read("SELECT c.TOKEN FROM mf_gallery_alias a JOIN mf_gallery_capability c ON c.TOKEN_HASH=a.TOKEN_HASH
            WHERE a.ALIAS='{$alias}' AND c.REVOKED=0 AND c.TOKEN IS NOT NULL");

        return false === $row ? null : (string)$row['TOKEN'];
    }

    public function revoke(string $hash, int $revision): void
    {
        $this->execute("UPDATE mf_gallery_capability SET REVOKED=1,REVISION=REVISION+1 WHERE TOKEN_HASH='{$hash}' AND REVISION={$revision} AND REVOKED=0");
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
    }

    /** @return array<string, mixed>|false */
    private function read(string $sql): array|false
    {
        try {
            return Application::getConnection()->query($sql)->fetch();
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read gallery alias.', 0, $error);
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
