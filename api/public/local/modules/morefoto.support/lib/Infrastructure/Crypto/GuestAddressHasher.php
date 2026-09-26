<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Crypto;

use Morefoto\Support\Application\Question\Contract\GuestAddressHasherInterface;

/**
 * HMAC-SHA256 адреса по ключу, выведенному HKDF из серверного секрета: без секрета хеш не перебрать по всем IPv4.
 * IPv6 сводится к префиксу /64 — абонент не обходит лимит сменой адреса внутри своей сети. IPv4-mapped IPv6 сводится
 * к IPv4 независимо от того, кто выбрал адрес: иначе все такие гости делили бы одно окно `::/64`.
 */
final readonly class GuestAddressHasher implements GuestAddressHasherInterface
{
    private const string INFO = 'morefoto.support.guest-address';
    private const int MIN_SECRET_LENGTH = 32;
    private const string MAPPED_IPV4 = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public function __construct(private string $secret) {}

    public function hash(string $address): string
    {
        if (self::MIN_SECRET_LENGTH > strlen($this->secret)) {
            throw new \RuntimeException('REBIT_ENCRYPTION_KEY is required to limit guest feedback.');
        }
        $binary = false === filter_var($address, FILTER_VALIDATE_IP) ? false : inet_pton($address);
        if (false === $binary) {
            throw new \RuntimeException('Guest address is not an IP address.');
        }
        if (16 === strlen($binary)) {
            $binary = str_starts_with($binary, self::MAPPED_IPV4) ? substr($binary, 12) : substr($binary, 0, 8);
        }

        return hash_hmac('sha256', $binary, hash_hkdf('sha256', $this->secret, 32, self::INFO), false);
    }
}
