<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Max;

use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Notification\Dto\MaxBotOutputDto;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatMessageInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;
use Rebit\Share\Application\Contract\Notification\MaxBotAdminInterface;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;

/**
 * HTTP-клиент Bot API MAX (platform-api2.max.ru): токен только в заголовке Authorization, TLS не отключается.
 * Сертификат MAX выпущен УЦ Минцифры, поэтому доверие для этих запросов ограничено Russian Trusted Root CA из модуля.
 * Токен, тексты и тела ответов не логируются.
 */
final readonly class MaxBotApiClient implements MaxChatMessengerInterface, MaxBotAdminInterface
{
    private const int CONNECT_TIMEOUT_SECONDS = 5;
    private const int TIMEOUT_SECONDS = 15;

    public function __construct(
        private LoggerInterface $logger,
        private MaxSendOutcomeClassifier $classifier,
        private string $token,
        private string $apiUrl,
        private string $caFile,
    ) {}

    public function isConfigured(): bool
    {
        return '' !== $this->token;
    }

    public function send(MaxChatMessageInputDto $message): MaxChatSendOutputDto
    {
        if ('' === $this->token) {
            return new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_not_configured');
        }
        [$error, $status, $body] = $this->request('POST', '/messages?chat_id=' . $message->chatId, ['text' => $message->text, 'notify' => true]);
        $outcome = $this->classifier->classify($error, $status, $body);
        if (MaxSendStatusEnum::DELIVERED !== $outcome->status) {
            $this->logger->warning('MAX не принял сообщение поддержки', ['httpStatus' => 0 === $status ? null : $status, 'exception' => $outcome->errorCode]);
        }

        return $outcome;
    }

    public function bot(): MaxBotOutputDto
    {
        $data = $this->successful('GET', '/me');
        $id = $data['user_id'] ?? null;
        if (!is_int($id)) {
            throw new \RuntimeException('MAX returned an unexpected bot profile.');
        }
        $name = trim((string)($data['first_name'] ?? '') . ' ' . (string)($data['last_name'] ?? ''));

        return new MaxBotOutputDto($id, (string)($data['username'] ?? ''), $name);
    }

    public function subscribe(string $url, string $secret, array $updateTypes): void
    {
        $data = $this->successful('POST', '/subscriptions', ['url' => $url, 'update_types' => $updateTypes, 'secret' => $secret]);
        if (true !== ($data['success'] ?? null)) {
            throw new \RuntimeException('MAX rejected the webhook subscription.');
        }
    }

    public function subscriptions(): array
    {
        $data = $this->successful('GET', '/subscriptions');
        $urls = [];
        foreach (is_array($data['subscriptions'] ?? null) ? $data['subscriptions'] : [] as $subscription) {
            if (is_array($subscription) && is_string($subscription['url'] ?? null)) {
                $urls[] = $subscription['url'];
            }
        }

        return $urls;
    }

    /**
     * @param null|array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function successful(string $method, string $path, ?array $payload = null): array
    {
        if ('' === $this->token) {
            throw new \RuntimeException('MAX bot token is not configured.');
        }
        [$error, $status, $body] = $this->request($method, $path, $payload);
        if (0 !== $error || 200 !== $status || null === $body) {
            throw new \RuntimeException('MAX request failed: ' . (0 !== $error ? 'transport error ' . $error : 'HTTP ' . $status));
        }
        $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('MAX returned an unexpected response.');
        }

        return $data;
    }

    /**
     * @param null|array<string, mixed> $payload
     *
     * @return array{0: int, 1: int, 2: ?string} cURL error, HTTP status and body
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $handle = curl_init(rtrim($this->apiUrl, '/') . $path);
        if (false === $handle) {
            return [\CURLE_FAILED_INIT, 0, null];
        }
        $headers = ['Authorization: ' . $this->token, 'Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ('' !== $this->caFile) {
            $options[CURLOPT_CAINFO] = $this->caFile;
        }
        if (null !== $payload) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [$error, $status, is_string($body) ? $body : null];
    }
}
