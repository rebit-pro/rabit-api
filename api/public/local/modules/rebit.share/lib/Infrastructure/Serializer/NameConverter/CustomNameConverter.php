<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Serializer\NameConverter;

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;

final readonly class CustomNameConverter implements AdvancedNameConverterInterface
{
    public function __construct(
        private ClassMetadataFactoryInterface $classMetadataFactory,
    ) {}

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $propertyName;
        }

        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $attributesMetadata = $classMetadata->getAttributesMetadata();

        if (isset($attributesMetadata[$propertyName])) {
            return $attributesMetadata[$propertyName]->getSerializedName() ?? $propertyName;
        }

        return $propertyName;
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $propertyName;
        }

        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($classMetadata->getAttributesMetadata() as $fieldName => $attributeMetadata) {
            $serializedName = $attributeMetadata->getSerializedName();

            if ($serializedName === $propertyName) {
                return $fieldName;
            }
        }

        return $propertyName;
    }
}
