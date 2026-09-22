<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Storefront\Exception;

/** Сохранённый расчёт устарел, и пересчёт того же состава дал другие деньги. */
final class QuotePriceChangedException extends \RuntimeException {}
