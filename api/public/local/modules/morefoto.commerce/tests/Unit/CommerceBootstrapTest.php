<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CommerceBootstrapTest extends TestCase
{
    private const string BOOTSTRAP = __DIR__ . '/../../include.php';

    private const array PROVIDERS = ['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.media', 'morefoto.handoff'];

    public function testInstalledCommerceBootsBeforeLegalMigrationRegistersLegal(): void
    {
        // Issue #117: Version20260925230001 registers morefoto.legal and itself boots through init.php with Commerce.
        [$exitCode, $output, $errors] = $this->bootstrap(self::PROVIDERS);

        self::assertSame(0, $exitCode, $errors);
        self::assertSame(self::PROVIDERS, json_decode($output, true));
    }

    public function testMissingProviderModuleStillStopsTheBootstrap(): void
    {
        [$exitCode, , $errors] = $this->bootstrap(array_values(array_diff(self::PROVIDERS, ['morefoto.media'])));

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('Required module is unavailable: morefoto.media', $errors);
    }

    /**
     * A separate PHP process with a minimal Loader answering like b_module keeps the fake away from other tests.
     *
     * @param list<string> $installed
     *
     * @return array{int, string, string}
     */
    private function bootstrap(array $installed): array
    {
        $script = sprintf(<<<'PHP'
            namespace Bitrix\Main;

            final class Loader
            {
                public static array $requested = [];

                public static function includeModule(string $moduleName): bool
                {
                    self::$requested[] = $moduleName;

                    return in_array($moduleName, %s, true);
                }
            }

            require %s;
            echo json_encode(Loader::$requested);
            PHP, var_export($installed, true), var_export(self::BOOTSTRAP, true));
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string)stream_get_contents($pipes[1]);
        $errors = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $errors];
    }
}
