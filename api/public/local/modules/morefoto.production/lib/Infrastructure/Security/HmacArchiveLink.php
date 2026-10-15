<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\Security;

use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;

/**
 * Ключ ссылки — HMAC версии и поколения ссылки на ключе, выведенном HKDF из серверного секрета: та же версия и
 * поколение всегда дают ту же ссылку, новое поколение отзывает прежнюю. В базе хранится только SHA-256 ключа.
 */
final readonly class HmacArchiveLink implements ArchiveLinkInterface
{
    private const string INFO = 'morefoto.production.archive-link';
    private const int MIN_SECRET_LENGTH = 32;

    public function __construct(private string $secret) {}

    public function token(string $versionPublicId, int $generation): string
    {
        if (self::MIN_SECRET_LENGTH > strlen($this->secret)) {
            throw new \RuntimeException('REBIT_ENCRYPTION_KEY is required to sign print archive links.');
        }
        $mac = hash_hmac('sha256', $versionPublicId . '|' . $generation, hash_hkdf('sha256', $this->secret, 32, self::INFO), true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
