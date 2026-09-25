<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Enum\LogChannelEnum;

/**
 * G1-T12: чистые контроллеры, канал логов и граница с Commerce.
 *
 * @internal
 */
final class PaymentArchitectureTest extends TestCase
{
    private const string LIB = __DIR__ . '/../../lib';

    #[DataProvider('controllers')]
    public function testControllerOnlyMapsTypedDtosThroughUseCases(string $controller, string $base, int $actions): void
    {
        $source = file_get_contents(self::LIB . '/Presentation/Controller/' . $controller . '.php');
        self::assertIsString($source);

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Payment\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Payment\Application\Payment\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Payment\Presentation\Payment\Request\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || in_array($name, [
                        'Morefoto\Payment\Presentation\Payment\PaymentInputMapper',
                        'Morefoto\Payment\Presentation\Payment\PaymentResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        $base,
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }
        foreach (['HttpRequest', 'ServiceLocator', 'getCurrentRoute', 'TokenResolverInterface', 'BearerTokenFilter', 'LoggerFilter',
            'CommonSerializer', 'RequestIdGenerator', 'getRequest()', 'configureActions', 'getExceptionResponse', 'finalizeResponse',
            'json_decode', 'HttpException', '$this->json([', 'try {'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertSame($actions, preg_match_all('/public function \w+Action\(\w+RequestDto \$request\): ControllerJson/', $source));
        self::assertSame(LogChannelEnum::payment, LogChannelEnum::resolveFromClassName('Morefoto\Payment\Presentation\Controller\\' . $controller));
    }

    public static function controllers(): iterable
    {
        yield 'buyer' => ['PublicPaymentController', 'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController', 3];
        yield 'provider' => ['PaymentWebhookController', 'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController', 1];
        yield 'staff' => ['StaffPaymentController', 'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController', 2];
    }

    public function testPaymentsNeverTouchCommerceStorageDirectly(): void
    {
        foreach ($this->sources() as $path => $source) {
            self::assertDoesNotMatchRegularExpression('/\bmf_order(_line|_access_key)?\b/', $source, $path . ' must use OrderPaymentInterface.');
            self::assertStringNotContainsString('Morefoto\Commerce\\', $source, $path . ' depends on the Commerce contract only.');
        }
    }

    public function testApplicationAndDomainStayFreeOfBitrixAndHttp(): void
    {
        foreach ($this->sources() as $path => $source) {
            if (str_contains($path, '/Application/') || str_contains($path, '/Domain/')) {
                foreach (['Bitrix\\', 'curl_', 'RebitHttpClient', 'getenv('] as $forbidden) {
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
