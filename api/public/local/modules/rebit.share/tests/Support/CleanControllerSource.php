<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Support;

/**
 * Checks a concrete controller source against the clean-controller rule: only request/result DTO, UseCase,
 * presentation mappers and the shared controller API; no Bitrix, request parsing or infrastructure assembly.
 */
final readonly class CleanControllerSource
{
    private const array FORBIDDEN = [
        'ServiceLocator',
        'HttpRequest',
        'getCurrentRoute',
        'TokenResolverInterface',
        'BearerTokenFilter',
        'LoggerFilter',
        'CommonSerializer',
        'RequestIdGenerator',
        'RequestFactory',
        'getRequest()',
        'configureActions',
        'getExceptionResponse',
        'finalizeResponse',
        'Json([',
        '$result[',
    ];

    /**
     * @param list<string>                $allowedNames    fully qualified names the controller may reference
     * @param list<array{string, string}> $allowedFamilies namespace prefix and class suffix pairs, e.g. UseCase or RequestDto
     *
     * @return list<string> violations, empty when the controller is clean
     */
    public static function violations(string $path, array $allowedNames, array $allowedFamilies): array
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            return ['Cannot read ' . $path];
        }
        $violations = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }
            $name = ltrim($token[1], '\\');
            $allowed = in_array($name, $allowedNames, true);
            foreach ($allowedFamilies as [$prefix, $suffix]) {
                $allowed = $allowed || (str_starts_with($name, $prefix) && str_ends_with($name, $suffix));
            }
            if (!$allowed) {
                $violations[] = 'Недопустимая зависимость concrete controller: ' . $name;
            }
        }
        foreach (self::FORBIDDEN as $forbidden) {
            if (str_contains($source, $forbidden)) {
                $violations[] = 'Запрещённая конструкция в concrete controller: ' . $forbidden;
            }
        }

        return $violations;
    }
}
