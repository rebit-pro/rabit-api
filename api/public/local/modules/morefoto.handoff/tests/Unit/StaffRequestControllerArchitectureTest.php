<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

/**
 * @internal
 */
final class StaffRequestControllerArchitectureTest extends TestCase
{
    public function testConcreteControllerHasNoBitrixOrInfrastructureAssembly(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffRequestController.php',
        );
        self::assertIsString($source);

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Handoff\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Handoff\Application\Request\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Handoff\Presentation\Request\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || (str_starts_with($name, 'Morefoto\Handoff\Presentation\Result\Dto\\') && str_ends_with($name, 'ResultDto'))
                    || in_array($name, [
                        'Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper',
                        'Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }

        foreach ([
            'TokenResolverInterface',
            'BearerTokenFilter',
            'LoggerFilter',
            'CommonSerializer',
            'RequestIdGenerator',
            'StaffRequestFactory',
            'getRequest()',
            'configureActions',
            'getExceptionResponse',
            'finalizeResponse',
            '$result[',
            '$this->json([',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function testHandoffNamespaceUsesDedicatedMonologChannel(): void
    {
        self::assertSame(
            LogChannelEnum::handoff,
            LogChannelEnum::resolveFromClassName(
                'Morefoto\Handoff\Presentation\Controller\StaffRequestController',
            ),
        );
    }
}
