<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Media\Dto\StaffChildOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;

/** @internal */
final class StaffRequestDtoArchitectureTest extends TestCase
{
    public function testWaveDtosContainOnlyReadonlyPropertiesAndEmptyConstructor(): void
    {
        $root = dirname(__DIR__, 2) . '/lib/';
        $classes = [StaffRequestActorOutputDto::class, StaffChildOutputDto::class, MediaScopeOutputDto::class];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), 'Dto.php')) {
                continue;
            }
            $classes[] = 'Morefoto\Handoff\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
        }
        self::assertGreaterThan(3, count($classes));
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
