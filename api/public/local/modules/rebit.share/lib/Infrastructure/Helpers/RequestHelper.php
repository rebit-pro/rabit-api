<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Bitrix\Main\HttpRequest;

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
