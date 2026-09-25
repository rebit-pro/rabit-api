<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** @internal */
final class StaffRequestDtoArchitectureTest extends TestCase
{
    /** Handoff DTOs and every shared contract DTO in rebit.share/lib/Contracts. */
    public function testWaveDtosContainOnlyReadonlyPropertiesAndEmptyConstructor(): void
    {
        $classes = [];
        foreach ([
            'Morefoto\Handoff\\' => dirname(__DIR__, 2) . '/lib/',
            'Rebit\Share\Contracts\\' => dirname(__DIR__, 3) . '/rebit.share/lib/Contracts/',
        ] as $namespace => $root) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
                if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), 'Dto.php')) {
                    continue;
                }
                $classes[] = $namespace . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
            }
        }
        self::assertContains('Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto', $classes);
        self::assertGreaterThan(25, count($classes));
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                self::assertTrue($property->isPublic() && $property->hasType(), $class);
            }
            foreach ($reflection->getMethods() as $method) {
                self::assertSame('__construct', $method->getName(), $class);
            }
            $filename = $reflection->getFileName();
            self::assertIsString($filename);
            // Bootstrap включает BypassFinals: исходный final readonly проверяем без rb-wrapper.
            $stream = fopen($filename, 'r');
            self::assertIsResource($stream);
            $source = stream_get_contents($stream);
            fclose($stream);
            self::assertIsString($source);
            self::assertStringContainsString('final readonly class ', $source, $class);
            self::assertMatchesRegularExpression('/public function __construct\([\s\S]*?\)\s*\{\s*\}\s*\}\s*$/', $source, $class);
        }
    }
}
