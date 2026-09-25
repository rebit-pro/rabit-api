<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Exception;

/** Провайдер окончательно отказал в операции; повтор с теми же данными результата не изменит. */
final class ProviderRejectedException extends \RuntimeException {}
