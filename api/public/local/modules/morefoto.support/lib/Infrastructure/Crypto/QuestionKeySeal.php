<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Crypto;

use Morefoto\Support\Application\Question\Contract\QuestionKeySealInterface;

/** AES-256-GCM с ключом из Idempotency-Key (HKDF), как повтор оформления заказа в Commerce. */
final readonly class QuestionKeySeal implements QuestionKeySealInterface
{
    private const string CIPHER = 'aes-256-gcm';
    private const string INFO = 'morefoto.support.question-replay';
    private const int NONCE = 12;
    private const int TAG = 16;
    private const int KEY_BYTES = 32;

    public function seal(string $questionKey, string $idempotencyKey, string $context): string
    {
        $plain = hex2bin($questionKey);
        if (false === $plain || self::KEY_BYTES !== strlen($plain)) {
            throw new \InvalidArgumentException('Question key must be 64 hex characters.');
        }
        $nonce = random_bytes(self::NONCE);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key($idempotencyKey, $context), OPENSSL_RAW_DATA, $nonce, $tag, $context, self::TAG);
        if (false === $cipher) {
            throw new \RuntimeException('Cannot seal question key.');
        }

        return base64_encode($nonce . $tag . $cipher);
    }

    public function open(string $sealed, string $idempotencyKey, string $context): string
    {
        $raw = base64_decode($sealed, true);
        if (false === $raw || self::NONCE + self::TAG + self::KEY_BYTES !== strlen($raw)) {
            throw new \RuntimeException('Sealed question key is malformed.');
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
            throw new \RuntimeException('Sealed question key cannot be opened.');
        }

        return bin2hex($plain);
    }

    private function key(string $idempotencyKey, string $context): string
    {
        return hash_hkdf('sha256', $idempotencyKey, self::KEY_BYTES, self::INFO, $context);
    }
}
