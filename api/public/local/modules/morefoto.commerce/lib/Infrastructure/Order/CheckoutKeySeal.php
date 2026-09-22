<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Order;

use Morefoto\Commerce\Application\Order\Contract\CheckoutKeySealInterface;

/** AES-256-GCM; ключ шифрования выводится HKDF-SHA256 из Idempotency-Key, контекст связывает копию с оформлением. */
final readonly class CheckoutKeySeal implements CheckoutKeySealInterface
{
    private const string CIPHER = 'aes-256-gcm';
    private const string INFO = 'morefoto.order.checkout-replay';
    private const int NONCE = 12;
    private const int TAG = 16;
    private const int KEY_BYTES = 32;

    public function seal(string $accessKey, string $idempotencyKey, string $context): string
    {
        $plain = hex2bin($accessKey);
        if (false === $plain || self::KEY_BYTES !== strlen($plain)) {
            throw new \InvalidArgumentException('Order key must be 64 hex characters.');
        }
        $nonce = random_bytes(self::NONCE);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key($idempotencyKey, $context), OPENSSL_RAW_DATA, $nonce, $tag, $context, self::TAG);
        if (false === $cipher) {
            throw new \RuntimeException('Cannot seal order key.');
        }

        return base64_encode($nonce . $tag . $cipher);
    }

    public function open(string $sealed, string $idempotencyKey, string $context): string
    {
        $raw = base64_decode($sealed, true);
        if (false === $raw || self::NONCE + self::TAG + self::KEY_BYTES !== strlen($raw)) {
            throw new \RuntimeException('Sealed order key is malformed.');
        }
        $plain = openssl_decrypt(
            substr($raw, self::NONCE + self::TAG),
            self::CIPHER,
            $this->key($idempotencyKey, $context),
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE),
            substr($raw, self::NONCE, self::TAG),
            $context,
        );
        if (false === $plain) {
            throw new \RuntimeException('Sealed order key cannot be opened.');
        }

        return bin2hex($plain);
    }

    private function key(string $idempotencyKey, string $context): string
    {
        return hash_hkdf('sha256', $idempotencyKey, self::KEY_BYTES, self::INFO, $context);
    }
}
