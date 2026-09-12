<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

/** Safe access denial or unavailable provider, codes 401/403/503. */
final class CatalogAccessException extends \RuntimeException {}
