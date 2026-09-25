<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

/**
 * Logs contain diagnostic metadata only. Unknown keys and arbitrary payloads are
 * dropped as a whole, including nested fields, files, URLs and exception text.
 */
final readonly class LogSanitizer
{
    public const string REDACTED = '[REDACTED]';
    private const int MAX_FIELDS = 32;
    private const int MAX_LABEL_LENGTH = 200;
    private const string UUID_PATTERN = '/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D';

    /** @var list<string> These values must be application metadata, never copied from a request. */
    private const array FIELDS = [
        'status', 'httpStatus', 'durationMs', 'line', 'exceptionCode', 'leadId', 'userId',
        'attempts', 'added', 'index', 'unknownFields', 'requestId', 'method', 'result',
        'source', 'operation', 'controller', 'class', 'dependency', 'parameter', 'file',
        'photoId', 'operationId', 'photoStatus', 'stage', 'exception', 'previous', 'event', 'published',
        'bytes', 'revision', 'attempt', 'inspectMs', 'storeMs', 'registerMs', 'publishMs',
        'decodeMs', 'thumbMs', 'previewMs', 'sinceAcceptedSeconds', 'sinceQueuedSeconds', 'pendingSeconds',
        'megapixels', 'redacted', 'truncated',
    ];

    /** @var list<string> Fixed application events; never add user-generated text. */
    private const array MESSAGES = [
        'AuditMessage получено',
        'Email-получатель внешних заявок не настроен: пустой REBIT_LEADHUNTER_FALLBACK_EMAIL',
        'Email-получатель заявок не настроен: пустой REBIT_NOTIFICATION_LEAD_FALLBACK_EMAIL',
        'Email-получатель заявок не настроен',
        'Failed to resolve dependency for controller constructor',
        'GeeTest captcha credentials are not configured',
        'GeeTest captcha verification failed',
        'GeeTest captcha verification request failed',
        'HTTP Error response',
        'HTTP Request',
        'HTTP Request failed',
        'HTTP Response',
        'HTTP_EXCEPTION',
        'MAX не принял сообщение поддержки',
        'Notification operation remains pending after publish failure.',
        'Notification operation remains pending after recovery publish failure.',
        'Pending photo job dispatched.',
        'Pending photo job was not dispatched.',
        'Photo job remains pending after immediate publish failure.',
        'Photo preview preparation failed.',
        'Photo previews ready.',
        'Photo upload accepted.',
        'REBIT_LEADHUNTER_RULES: невалидный JSON',
        'REBIT_LEADHUNTER_RULES: пропущено невалидное правило',
        'REQUEST',
        'RESPONSE',
        'Support message remains pending after publish failure.',
        'Telegram отклонил запрос',
        'Telegram-получатель внешних заявок не настроен: пустой токен или chat_id',
        'Telegram-получатель заявок не настроен: пустой токен или chat_id',
        'Для площадки не зарегистрирована лента',
        'Заявка передана почтовому транспорту',
        'Кеширование не readonly объекта без метода __clone!',
        'Лента fl.ru недоступна',
        'Найдены новые внешние заявки',
        'Не удалось инициализировать запрос в Telegram',
        'Не удалось инициализировать запрос к fl.ru',
        'Не удалось отправить запрос в Telegram',
        'Не удалось отправить заявку письмом',
        'Не удалось прочитать файл ТЗ для письма',
        'Не удалось разобрать RSS fl.ru',
        'Основной канал доставки заявки недоступен, уходим на резервный',
        'Основной канал доставки недоступен, уходим на резервный',
        'Правила охоты не настроены: REBIT_LEADHUNTER_RULES пуст или невалиден',
    ];

    public function message(string $message): string
    {
        return in_array($message, self::MESSAGES, true) ? $message : self::REDACTED;
    }

    /**
     * This is a projection, not a recursive blacklist. Attacker-controlled keys
     * are not retained, and no object/string conversion or serialization runs.
     *
     * @param array<array-key, mixed> $context
     *
     * @return array<string, bool|float|int|string>
     */
    public function context(array $context): array
    {
        $safe = [];
        // An allowed key without a value carries no data, so it is omitted without marking the record as redacted.
        $empty = 0;
        foreach (self::FIELDS as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];
            if (null === $value) {
                ++$empty;
                continue;
            }
            $filtered = match ($key) {
                'status', 'httpStatus' => is_int($value) && 100 <= $value && 599 >= $value ? $value : null,
                'durationMs' => is_float($value) || is_int($value)
                    ? (is_finite((float)$value) && 0 <= $value ? round((float)$value, 3) : null)
                    : null,
                'megapixels' => is_float($value) || is_int($value)
                    ? (is_finite((float)$value) && 0 <= $value ? round((float)$value, 1) : null)
                    : null,
                'line', 'exceptionCode', 'leadId', 'userId', 'attempts', 'added', 'index', 'unknownFields' => is_int($value) ? $value : null,
                'bytes', 'revision', 'attempt', 'inspectMs', 'storeMs', 'registerMs', 'publishMs', 'decodeMs', 'thumbMs',
                'previewMs', 'sinceAcceptedSeconds', 'sinceQueuedSeconds', 'pendingSeconds' => is_int($value) && 0 <= $value ? $value : null,
                'requestId' => is_string($value) && 1 === preg_match('/\A[a-f0-9]{14}\.[0-9]{8}\z/D', $value) ? $value : null,
                'photoId', 'operationId' => is_string($value) && 1 === preg_match(self::UUID_PATTERN, $value) ? $value : null,
                'photoStatus' => in_array($value, ['processing', 'ready', 'failed', 'duplicate'], true) ? $value : null,
                'method' => in_array($value, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'CLI', 'sendMessage', 'sendDocument'], true) ? $value : null,
                'result' => in_array($value, ['Y', 'N', 'ok', 'error'], true) ? $value : null,
                'source' => 'flRu' === $value ? $value : null,
                'operation', 'controller', 'class', 'dependency', 'parameter', 'file', 'stage', 'exception', 'previous', 'event' => $this->label($value),
                'published' => is_bool($value) ? $value : null,
                'redacted', 'truncated' => true === $value ? true : null,
            };

            if (null === $filtered) {
                continue;
            }

            $safe[$key] = $filtered;
        }

        if (count($context) - $empty > count($safe)) {
            $safe['redacted'] = true;
        }
        if (self::MAX_FIELDS < count($context)) {
            $safe['truncated'] = true;
        }

        return $safe;
    }

    /**
     * Exception metadata is obtained from the object, never from its message,
     * string representation, trace arguments or previous exception chain.
     *
     * @return array<string, bool|float|int|string>
     */
    public function exception(\Throwable $exception): array
    {
        return $this->context([
            'class' => $exception::class,
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'exceptionCode' => $exception->getCode(),
        ]);
    }

    private function label(mixed $value): ?string
    {
        if (!is_string($value) || self::MAX_LABEL_LENGTH < strlen($value)) {
            return null;
        }

        return 1 === preg_match('/\A[a-zA-Z_][a-zA-Z0-9_\\\:.\-]*\z/D', $value) ? $value : null;
    }
}
