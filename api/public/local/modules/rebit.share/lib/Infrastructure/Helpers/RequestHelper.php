<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Shared\Exception\HttpException;

final class RequestHelper
{
    private const string DOMAIN = 'rebit.local';

    /**
     * Собирает входящие поля запроса.
     *
     * JSON body и form-данные (POST) не смешиваются:
     * если есть JSON body — GET + JSON, иначе — GET + POST.
     * Тело запроса (JSON/POST) имеет приоритет над GET при совпадении ключей.
     *
     * @return array<string, mixed>
     */
    public static function collectRequestValues(HttpRequest $request): array
    {
        $jsonValues = $request->getJsonList()->getValues();
        if ([] !== $jsonValues) {
            return array_merge(
                $request->getQueryList()->getValues(),
                $jsonValues,
            );
        }

        return array_merge(
            $request->getQueryList()->getValues(),
            $request->getPostList()->getValues(),
        );
    }

    /** @return array<string, mixed> */
    public static function collectJsonRequestValues(HttpRequest $request, int $maxBytes, bool $nestedArrays = false): array
    {
        $contentType = strtolower(trim(explode(';', (string)$request->getHeader('Content-Type'))[0]));
        if ([] !== self::collectQueryValues($request) || 'application/json' !== $contentType) {
            throw new HttpException('JSON_REQUIRED', 400);
        }

        $raw = (string)$request->getInput();
        if ($maxBytes < strlen($raw)) {
            throw new HttpException('PAYLOAD_TOO_LARGE', 413);
        }

        return self::decodeJsonObject($raw, $nestedArrays);
    }

    /**
     * Тело — JSON-объект. Строгие DTO получают вложенные объекты как stdClass для проверки формы; нестрогие
     * (тело внешнего провайдера с лишними полями) — как массивы, которые гидратор сопоставляет вложенным DTO.
     *
     * @return array<string, mixed>
     *
     * @throws HttpException
     */
    public static function decodeJsonObject(string $raw, bool $nestedArrays = false): array
    {
        try {
            $object = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new HttpException('MALFORMED_JSON', 400, $exception);
        }

        if (!$object instanceof \stdClass) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return $nestedArrays ? (array)json_decode($raw, true, 16, JSON_THROW_ON_ERROR) : get_object_vars($object);
    }

    /** @return array<string, mixed> */
    public static function collectQueryValues(HttpRequest $request): array
    {
        $values = [];
        parse_str((string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY), $values);

        return $values;
    }

    public static function getSiteUrl(): string
    {
        return ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    }

    public static function getFullUrl(string $pageUri = '/'): string
    {
        if (self::isExternalUrl($pageUri)) {
            return $pageUri;
        }

        return self::getSiteUrl() . $pageUri;
    }

    private static function isExternalUrl(string $url): bool
    {
        return str_contains($url, self::DOMAIN);
    }
}
