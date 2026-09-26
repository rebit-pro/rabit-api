<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Security;

use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;

/** Подпись `срок.hmac` по ID загрузки: ключ выводится HKDF из серверного секрета, личный ключ заказа в ссылку не попадает. */
final readonly class HmacDownloadToken implements DownloadTokenInterface
{
    private const string INFO = 'morefoto.files.download-link';
    private const int MIN_SECRET_LENGTH = 32;

    public function __construct(private string $secret) {}

    public function issue(string $downloadId, \DateTimeImmutable $expiresAt): string
    {
        $expires = (string)$expiresAt->getTimestamp();

        return $expires . '.' . $this->sign($downloadId, $expires);
    }

    public function valid(string $downloadId, string $token, \DateTimeImmutable $now): bool
    {
        if (1 !== preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/D', $token, $parts)) {
            return false;
        }

        return (int)$parts[1] > $now->getTimestamp() && hash_equals($this->sign($downloadId, $parts[1]), $parts[2]);
    }

    private function sign(string $downloadId, string $expires): string
    {
        if (self::MIN_SECRET_LENGTH > strlen($this->secret)) {
            throw new \RuntimeException('REBIT_ENCRYPTION_KEY is required to sign download links.');
        }

        return hash_hmac('sha256', $downloadId . '|' . $expires, hash_hkdf('sha256', $this->secret, 32, self::INFO));
    }
}
