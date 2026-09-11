<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Telegram;

use Psr\Log\LoggerInterface;

/**
 * Низкоуровневый клиент Telegram Bot API.
 *
 * Используем cURL напрямую (а не RebitHttpClient/Bitrix HttpClient), потому что
 * с сервера api.telegram.org заблокирован и запрос идёт через прокси, в т.ч.
 * SOCKS5 — это умеет cURL, но не Bitrix HttpClient. Тип прокси определяется
 * по схеме в $proxy (например socks5h://user:pass@host:port или http://...).
 *
 * Клиент не знает про chat_id и формат сообщений — это ответственность
 * вызывающего кода. Токен, базовый URL и прокси берутся из окружения сервера.
 */
final readonly class TelegramBotApiClient
{
    private const int CONNECT_TIMEOUT_SECONDS = 12;

    public function __construct(
        private LoggerInterface $logger,
        private string $botToken,
        private string $apiBaseUrl,
        private string $proxy,
    ) {}

    public function isConfigured(): bool
    {
        return '' !== $this->botToken;
    }

    /**
     * Вызов метода Bot API.
     *
     * @param array<string, mixed>|string $postFields http_build_query-строка (обычный POST)
     *                                                или массив (multipart с CURLFile)
     *
     * @return bool успешно ли Telegram принял запрос (ok=true)
     */
    public function call(string $method, array|string $postFields, int $timeout): bool
    {
        $handle = curl_init($this->buildMethodUrl($method));

        if (false === $handle) {
            $this->logger->error('Не удалось инициализировать запрос в Telegram', ['method' => $method]);

            return false;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_POSTFIELDS => $postFields,
        ];

        if ('' !== $this->proxy) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }

        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || 200 !== $status) {
            $this->logger->error('Не удалось отправить запрос в Telegram', [
                'method' => $method,
                'status' => $status,
                'error' => $error,
                'response' => is_string($response) ? mb_substr($response, 0, 500) : '',
            ]);

            return false;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || true !== ($decoded['ok'] ?? false)) {
            $this->logger->error('Telegram отклонил запрос', [
                'method' => $method,
                'response' => mb_substr($response, 0, 500),
            ]);

            return false;
        }

        return true;
    }

    private function buildMethodUrl(string $method): string
    {
        return sprintf('%s/bot%s/%s', rtrim($this->apiBaseUrl, '/'), $this->botToken, $method);
    }
}
