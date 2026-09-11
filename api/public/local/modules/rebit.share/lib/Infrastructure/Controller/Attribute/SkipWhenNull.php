<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Attribute;

/**
 * Аттрибут используется в DTO для пропуска полей с null-значениями при сериализации.
 *
 * Пример:
 *
 * #[SkipWhenNull]
 * public ?string $error;
 *
 * В случае, если $error = null, то в ответе контроллера не будет поля error
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SkipWhenNull {}
