<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Storefront\Repository;

use Bitrix\Main\Application;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;

final readonly class QuoteRepository
{
    /** @param array<string,mixed> $snapshot */
    public function save(string $hash, string $galleryHash, string $fingerprint, array $snapshot, string $expiresAt): void
    {
        try {
            $connection = Application::getConnection();
            $json = $connection->getSqlHelper()->forSql(json_encode($snapshot, JSON_THROW_ON_ERROR));
            $connection->queryExecute("INSERT INTO mf_cart_quote(TOKEN_HASH,GALLERY_HASH,FINGERPRINT,SNAPSHOT_JSON,EXPIRES_AT,CREATED_AT)
                VALUES('{$hash}','{$galleryHash}','{$fingerprint}','{$json}','{$expiresAt}',UTC_TIMESTAMP())");
        } catch (\Throwable $error) {
            throw new CatalogStorageException('Cannot persist cart quote.', 0, $error);
        }
    }

    /** @return array{GALLERY_HASH:string,FINGERPRINT:string,EXPIRES_AT:string,SNAPSHOT_JSON:string}|false */
    public function find(string $hash): array|false
    {
        try {
            return Application::getConnection()->query("SELECT GALLERY_HASH,FINGERPRINT,DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT,SNAPSHOT_JSON FROM mf_cart_quote WHERE TOKEN_HASH='{$hash}'")->fetch();
        } catch (\Throwable $error) {
            throw new CatalogStorageException('Cannot resolve cart quote.', 0, $error);
        }
    }
}
