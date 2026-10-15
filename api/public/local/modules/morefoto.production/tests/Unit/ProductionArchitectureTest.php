<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

require_once __DIR__ . '/../bootstrap.php';

/**
 * L1: чистые контроллеры, канал логов, ссылка вне логов nginx и границы с Commerce, Organization, Media, Access.
 *
 * @internal
 */
final class ProductionArchitectureTest extends TestCase
{
    private const string LIB = __DIR__ . '/../../lib';

    public function testControllersOnlyMapTypedDtosThroughUseCases(): void
    {
        foreach (['ProductionController', 'PublicArchiveController'] as $controller) {
            $source = (string)file_get_contents(self::LIB . '/Presentation/Controller/' . $controller . '.php');
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $name = ltrim($token[1], '\\');
                    self::assertTrue(
                        'Morefoto\Production\Presentation\Controller' === $name
                        || (str_starts_with($name, 'Morefoto\Production\Application\Archive\UseCase\\') && str_ends_with($name, 'UseCase'))
                        || (str_starts_with($name, 'Morefoto\Production\Presentation\Archive\Request\Dto\\') && str_ends_with($name, 'RequestDto'))
                        || in_array($name, [
                            'Morefoto\Production\Presentation\Archive\ProductionInputMapper',
                            'Morefoto\Production\Presentation\Archive\ProductionResultMapper',
                            'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                            'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                            'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController',
                            'Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse',
                            'Rebit\Share\Infrastructure\Controller\Responses\TextFileResponse',
                        ], true),
                        'Недопустимая зависимость concrete controller ' . $controller . ': ' . $name,
                    );
                }
            }
            foreach (['HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'getRequest()', 'configureActions', 'getExceptionResponse',
                'finalizeResponse', 'json_decode', 'HttpException', '$this->json([', 'try {', 'X-Accel', 'addHeader'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $controller);
            }
            self::assertSame(0, preg_match_all('/public function \w+Action\((?!\w+RequestDto \$request\))/', $source), $controller);
        }
        self::assertSame(LogChannelEnum::production, LogChannelEnum::resolveFromClassName('Morefoto\Production\Presentation\Controller\ProductionController'));
    }

    public function testArchiveLinksStayOutOfTheAccessLogAndFilesStayInternal(): void
    {
        $config = (string)file_get_contents(dirname(__DIR__, 6) . '/docker/common/nginx/conf.d/default.conf');

        self::assertStringContainsString('~^/api/v1/public/production/archives/ 0;', $config);
        self::assertMatchesRegularExpression('#location \^~ /_protected/files/ \{\s+internal;#', $config);
    }

    public function testProductionUsesOnlySupplierContracts(): void
    {
        foreach ($this->sources() as $path => $source) {
            self::assertDoesNotMatchRegularExpression('/\b(mf_order|mf_order_line|b_hlbd_mf_group|b_hlbd_mf_photo|mf_photo_assignment|mf_payment_attempt)\b/', $source, $path);
            foreach (['Morefoto\Commerce\\', 'Morefoto\Organization\\', 'Morefoto\Media\\', 'Morefoto\Access\\', 'Morefoto\Payment\\', 'Morefoto\Files\\'] as $module) {
                self::assertStringNotContainsString($module, $source, $path);
            }
        }
    }

    public function testApplicationAndDomainStayFreeOfBitrixAndFilesystem(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Application/') || str_contains($path, '/Domain/')) {
                foreach (['Bitrix\\', 'getenv(', 'ZipArchive', 'fopen(', 'unlink(', 'file_put_contents(', 'is_file('] as $forbidden) {
                    self::assertStringNotContainsString($forbidden, $source, $path);
                }
            }
        }
    }

    public function testUseCasesAndServicesExplainThemselves(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_ends_with($path, 'UseCase.php') || str_contains($path, '/Service/')) {
                // BypassFinals strips `final readonly` from sources read under PHPUnit, so the modifiers are optional here.
                self::assertMatchesRegularExpression('/\/\*\*(?:(?!\*\/).)*[А-Яа-яЁё](?:(?!\*\/).)*\*\/\s*(#\[[^\]]+\]\s*)*(final\s+)?(readonly\s+)?class/su', $source, $path . ' needs a class phpDoc in Russian.');
            }
        }
    }

    /** @return array<string, string> */
    private function sources(): array
    {
        $sources = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::LIB, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', (string)$file);
            $sources[$path] = (string)file_get_contents($path);
        }
        ksort($sources);

        return $sources;
    }
}
