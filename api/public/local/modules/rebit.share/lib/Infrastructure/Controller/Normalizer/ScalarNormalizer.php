<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Normalizer;

use Rebit\Share\Shared\Interface\NormalizerInterface;

final readonly class ScalarNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data): mixed
    {
        return $data;
    }
}
