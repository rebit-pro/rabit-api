<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

/**
 * Текстовое поле multipart-формы. Нестроковое значение, отсутствие значения у не-nullable параметра
 * или несовпадение с `pattern` отклоняются кодом `errorCode` со статусом 422.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class FormField
{
    public function __construct(
        public string $errorCode,
        public ?string $pattern = null,
    ) {}
}
