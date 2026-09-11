<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Exception;

use Rebit\Share\Shared\Exception\ValidationHttpException as SharedValidationHttpException;

/**
 * Legacy-wrapper для обратной совместимости со старым namespace.
 */
class ValidationHttpException extends SharedValidationHttpException {}
