<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\HttpClient;

use Bitrix\Main\Web\HttpClient;
use Psr\Log\LoggerInterface;
use Rebit\Share\Infrastructure\Logger\HttpDebugLoggerFactory;

final class RebitHttpClientFactory
{
    private const int DEFAULT_SOCKET_TIMEOUT = 30;
    private const int DEFAULT_STREAM_TIMEOUT = 60;

    public static function create(LoggerInterface $logger): RebitHttpClient
    {
        $httpClient = new HttpClient([
            'socketTimeout' => self::DEFAULT_SOCKET_TIMEOUT,
            'streamTimeout' => self::DEFAULT_STREAM_TIMEOUT,
            'disableSslVerification' => false,
        ]);

        return new RebitHttpClient(HttpDebugLoggerFactory::create($logger), $httpClient);
    }
}
