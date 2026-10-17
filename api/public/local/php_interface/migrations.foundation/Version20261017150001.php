<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Config\Option;

final class Version20261017150001 extends Version
{
    /** J5: the largest letter carries 20 MiB of originals, 27 962 028 bytes after base64; 30 MB is the letter limit of Yandex. */
    private const string LIMIT = '30000000';
    /** The Bitrix default of `main.max_file_size`. */
    private const string DEFAULT = '20000000';

    protected $author = 'codex';
    protected $description = 'J5: the mail attachment limit of Bitrix fits a letter with photos (30 MB)';

    public function up(): void
    {
        // Bitrix swaps attachments above the limit for a text stub; 0 means no limit and stays as it is.
        $current = (int)Option::get('main', 'max_file_size', self::DEFAULT);
        if (0 < $current && $current < (int)self::LIMIT) {
            Option::set('main', 'max_file_size', self::LIMIT);
        }
    }

    public function down(): void
    {
        if (self::LIMIT === Option::get('main', 'max_file_size', self::DEFAULT)) {
            Option::set('main', 'max_file_size', self::DEFAULT);
        }
    }
}
