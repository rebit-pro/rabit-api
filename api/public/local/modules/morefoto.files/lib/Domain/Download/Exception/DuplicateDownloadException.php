<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Exception;

/** Параллельный запрос занял тот же ключ идемпотентности или единственную сборку ZIP заказа. */
final class DuplicateDownloadException extends \RuntimeException {}
