<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

/**
 * IP клиента за цепочкой Traefik → nginx frontend → nginx backend: все звенья в docker-сетях, поэтому доверенный
 * прокси — любой непубличный адрес. X-Forwarded-For читается справа налево, как real_ip_recursive nginx: первый
 * публичный адрес — клиент, подделанные клиентом левые элементы не используются.
 */
final readonly class ClientAddressResolver
{
    /** @return string пустая строка — адрес не определён */
    public function resolve(?string $remoteAddress, ?string $forwardedFor): string
    {
        $client = $this->normalize((string)$remoteAddress);
        if (null === $client) {
            return '';
        }
        if (!$this->trusted($client) || null === $forwardedFor) {
            return $client;
        }
        foreach (array_reverse(explode(',', $forwardedFor)) as $hop) {
            $address = $this->normalize(trim($hop));
            if (null === $address) {
                break;
            }
            $client = $address;
            if (!$this->trusted($address)) {
                break;
            }
        }

        return $client;
    }

    private function normalize(string $address): ?string
    {
        if (false === filter_var($address, FILTER_VALIDATE_IP)) {
            return null;
        }

        return (string)inet_ntop((string)inet_pton($address));
    }

    private function trusted(string $address): bool
    {
        return false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
