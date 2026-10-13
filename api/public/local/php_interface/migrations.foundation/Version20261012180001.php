<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261012180001 extends Version
{
    /** F3-DEC-01: 10 symbols of the lowercase alphabet without 0/o and 1/l. */
    private const string ALPHABET = '23456789abcdefghijkmnpqrstuvwxyz';

    protected $author = 'claude';
    protected $description = 'F3: short aliases of gallery keys for the link staff copy into the parents chat';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_gallery_capability')) {
            throw new \RuntimeException('F3 requires the merged E4 gallery keys.');
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_gallery_alias (
    ALIAS CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    TOKEN_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ALIAS),
    UNIQUE KEY ux_mf_gallery_alias_key (TOKEN_HASH),
    CONSTRAINT fk_mf_gallery_alias_key FOREIGN KEY (TOKEN_HASH) REFERENCES mf_gallery_capability(TOKEN_HASH) ON DELETE CASCADE,
    CONSTRAINT ck_mf_gallery_alias_format CHECK (ALIAS REGEXP '^[2-9a-km-np-z]{10}$')
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin
SQL);
        // Groups sent since F2 keep their link: every active key staff can copy gets its alias now; the key is untouched.
        $keys = $connection->query('SELECT c.TOKEN_HASH FROM mf_gallery_capability c LEFT JOIN mf_gallery_alias a ON a.TOKEN_HASH=c.TOKEN_HASH
            WHERE c.REVOKED=0 AND c.TOKEN IS NOT NULL AND a.ALIAS IS NULL');
        while (false !== ($key = $keys->fetch())) {
            $this->attach((string)$key['TOKEN_HASH']);
        }
    }

    public function down(): void
    {
        // The long links keep working: removing the aliases only stops the short ones.
        Application::getConnection()->queryExecute('DROP TABLE IF EXISTS mf_gallery_alias');
    }

    /** A clash with another key's alias is retried with a fresh value. */
    private function attach(string $hash): void
    {
        $connection = Application::getConnection();
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $connection->queryExecute("INSERT IGNORE INTO mf_gallery_alias(ALIAS,TOKEN_HASH,CREATED_AT) VALUES('" . $this->alias() . "','{$hash}',UTC_TIMESTAMP())");
            if (false !== $connection->query("SELECT 1 FROM mf_gallery_alias WHERE TOKEN_HASH='{$hash}'")->fetch()) {
                return;
            }
        }
        throw new \RuntimeException('Cannot give a gallery key a unique alias.');
    }

    private function alias(): string
    {
        $alias = '';
        foreach (str_split(random_bytes(10)) as $byte) {
            $alias .= self::ALPHABET[ord($byte) & 31];
        }

        return $alias;
    }
}
