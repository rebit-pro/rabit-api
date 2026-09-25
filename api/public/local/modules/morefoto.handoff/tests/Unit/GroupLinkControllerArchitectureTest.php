<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

/**
 * @internal
 */
final class GroupLinkControllerArchitectureTest extends TestCase
{
    public function testControllerOnlyMapsTypedDtosThroughUseCases(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/Presentation/Controller/GroupLinkController.php');
        self::assertIsString($source);

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Handoff\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Handoff\Application\Link\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Handoff\Presentation\Link\Request\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || in_array($name, [
                        'Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper',
                        'Morefoto\Handoff\Presentation\Link\GroupLinkResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }
        // Qualified names (including Bitrix\*) are covered by the token allow-list above.
        foreach (['HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'TokenResolverInterface', 'BearerTokenFilter', 'LoggerFilter',
            'CommonSerializer', 'RequestIdGenerator', 'getRequest()', 'configureActions', 'getExceptionResponse', 'finalizeResponse',
            'json_decode', 'HttpException', '$this->json([', 'try {'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertSame(5, preg_match_all('/public function \w+Action\(\w+RequestDto \$request\): ControllerJson/', $source));
    }

    public function testLinkControllerLogsToTheHandoffChannel(): void
    {
        self::assertSame(LogChannelEnum::handoff, LogChannelEnum::resolveFromClassName('Morefoto\Handoff\Presentation\Controller\GroupLinkController'));
    }
}
