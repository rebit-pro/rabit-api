<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

/**
 * J1-T10: чистый контроллер, канал логов и границы с Commerce и Media.
 *
 * @internal
 */
final class FilesArchitectureTest extends TestCase
{
    private const string LIB = __DIR__ . '/../../lib';

    public function testControllerOnlyMapsTypedDtosThroughUseCases(): void
    {
        $source = file_get_contents(self::LIB . '/Presentation/Controller/PublicFileController.php');
        self::assertIsString($source);

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Files\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Files\Application\Files\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Files\Presentation\Files\Request\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || in_array($name, [
                        'Morefoto\Files\Presentation\Files\FilesInputMapper',
                        'Morefoto\Files\Presentation\Files\FilesResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController',
                        'Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse',
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }
        foreach (['HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'LoggerFilter', 'getRequest()', 'configureActions', 'getExceptionResponse',
            'finalizeResponse', 'json_decode', 'HttpException', '$this->json([', 'try {', 'X-Accel', 'addHeader'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertSame(3, preg_match_all('/public function \w+Action\(\w+RequestDto \$request\): ControllerJson/', $source));
        self::assertSame(1, preg_match_all('/public function contentAction\(DownloadContentRequestDto \$request\): ProtectedFileResponse/', $source));
        self::assertSame(LogChannelEnum::files, LogChannelEnum::resolveFromClassName('Morefoto\Files\Presentation\Controller\PublicFileController'));
    }

    public function testBackendNginxServesPrivateRootsOnlyInternallyAndKeepsTokensOutOfTheLog(): void
    {
        $config = (string)file_get_contents(dirname(__DIR__, 6) . '/docker/common/nginx/conf.d/default.conf');

        self::assertStringContainsString('~^/api/v1/public/orders/current/downloads/ 0;', $config);
        self::assertSame(2, preg_match_all('#location \^~ /_protected/(media|files)/ \{\s+internal;#', $config));
    }

    public function testFilesUseOnlyTheCommerceAndMediaContracts(): void
    {
        foreach ($this->sources() as $path => $source) {
            self::assertDoesNotMatchRegularExpression('/\b(mf_order|mf_order_line|mf_order_access_key|b_hlbd_mf_photo|mf_photo_assignment)\b/', $source, $path);
            self::assertStringNotContainsString('Morefoto\Commerce\\', $source, $path);
            self::assertStringNotContainsString('Morefoto\Media\\', $source, $path);
        }
    }

    public function testApplicationAndDomainStayFreeOfBitrixAndFilesystem(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Application/') || str_contains($path, '/Domain/')) {
                foreach (['Bitrix\\', 'getenv(', 'ZipArchive', 'fopen(', 'unlink(', 'file_put_contents('] as $forbidden) {
                    self::assertStringNotContainsString($forbidden, $source, $path);
                }
            }
        }
    }

    public function testUseCasesAndServicesExplainThemselves(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_ends_with($path, 'UseCase.php') || str_contains($path, '/Service/') || str_contains($path, '/Handler/')) {
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
