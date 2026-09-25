<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Max;

use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;

/**
 * Классифицирует ответ POST /messages MAX: принят, окончательный отказ, безопасный повтор или неизвестный исход.
 *
 * Повтор разрешён только когда запрос заведомо не обработан: соединение не установлено или MAX ответил 429.
 * Таймаут после отправки, 5xx и ответы шлюза 502–504 — неизвестный исход: шлюз мог потерять ответ уже после
 * создания сообщения, а у POST /messages нет ключа идемпотентности (RFC 9110, 9.2.2 и 15.6).
 */
final readonly class MaxSendOutcomeClassifier
{
    /** cURL не установил соединение: запрос не ушёл. */
    private const array NOT_SENT_ERRORS = [\CURLE_COULDNT_RESOLVE_PROXY, \CURLE_COULDNT_RESOLVE_HOST, \CURLE_COULDNT_CONNECT, \CURLE_SSL_CONNECT_ERROR];

    public function classify(int $curlError, int $httpStatus, ?string $body): MaxChatSendOutputDto
    {
        if (0 !== $curlError) {
            return in_array($curlError, self::NOT_SENT_ERRORS, true)
                ? new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_connect_failed')
                : new MaxChatSendOutputDto(MaxSendStatusEnum::UNKNOWN, errorCode: 'max_transport_error');
        }
        if (200 === $httpStatus) {
            $mid = $this->mid($body);

            return null === $mid
                ? new MaxChatSendOutputDto(MaxSendStatusEnum::UNKNOWN, errorCode: 'max_response_invalid')
                : new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, $mid);
        }
        if (429 === $httpStatus) {
            return new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_http_429');
        }
        if (400 <= $httpStatus && 500 > $httpStatus) {
            return new MaxChatSendOutputDto(MaxSendStatusEnum::REJECTED, errorCode: 'max_http_' . $httpStatus);
        }

        return new MaxChatSendOutputDto(MaxSendStatusEnum::UNKNOWN, errorCode: 'max_http_' . $httpStatus);
    }

    private function mid(?string $body): ?string
    {
        if (null === $body) {
            return null;
        }
        try {
            $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        $mid = is_array($data) ? ($data['message']['body']['mid'] ?? null) : null;

        return is_string($mid) && '' !== $mid && 128 >= strlen($mid) ? $mid : null;
    }
}
