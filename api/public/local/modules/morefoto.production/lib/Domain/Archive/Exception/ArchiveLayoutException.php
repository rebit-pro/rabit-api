<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Exception;

/** Состав нельзя разложить без потери исходного имени или неоднозначности задания. */
final class ArchiveLayoutException extends \RuntimeException {}
