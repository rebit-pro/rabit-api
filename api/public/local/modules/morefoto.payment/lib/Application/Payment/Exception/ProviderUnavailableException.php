<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Exception;

/** Провайдер не ответил или ответил временной ошибкой: исход операции не известен. */
final class ProviderUnavailableException extends \RuntimeException {}
