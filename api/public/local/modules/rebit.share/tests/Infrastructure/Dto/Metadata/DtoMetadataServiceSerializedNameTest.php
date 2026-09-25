<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Dto\Metadata;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Dto\Metadata\DtoMetadataService;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Symfony\Component\Serializer\Annotation\SerializedName as AnnotationSerializedName;
use Symfony\Component\Serializer\Attribute\SerializedName as AttributeSerializedName;

/**
 * @internal
 */
final class DtoMetadataServiceSerializedNameTest extends TestCase
{
    /**
     * Метаданные строятся первым вызовом, поэтому deprecation ловится до гидрации.
     *
     * @param class-string<AnnotationSerializedNameFixtureDto|AttributeSerializedNameFixtureDto> $className
     */
    #[DataProvider('fixtureProvider')]
    public function testExternalNameIsMappedWithoutDeprecations(string $className): void
    {
        /** @var list<string> $deprecations */
        $deprecations = [];
        set_error_handler(
            static function(int $errno, string $errstr) use (&$deprecations): bool {
                $deprecations[] = $errstr;

                return true;
            },
            E_DEPRECATED | E_USER_DEPRECATED,
        );

        try {
            $serializedMap = DtoMetadataService::analyze($className)->serializedMap;
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
        self::assertSame(['external_id' => 'externalId'], $serializedMap);

        $dto = ArrayToDtoMapper::map(['external_id' => 42, 'name' => 'Maxim'], $className);

        self::assertSame(42, $dto->externalId);
        self::assertSame('Maxim', $dto->name);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'Annotation\SerializedName' => [AnnotationSerializedNameFixtureDto::class];
        yield 'Attribute\SerializedName' => [AttributeSerializedNameFixtureDto::class];
    }
}

/** @internal */
final readonly class AnnotationSerializedNameFixtureDto
{
    public function __construct(
        #[AnnotationSerializedName('external_id')]
        public int $externalId,
        public string $name,
    ) {}
}

/** @internal */
final readonly class AttributeSerializedNameFixtureDto
{
    public function __construct(
        #[AttributeSerializedName('external_id')]
        public int $externalId,
        public string $name,
    ) {}
}
