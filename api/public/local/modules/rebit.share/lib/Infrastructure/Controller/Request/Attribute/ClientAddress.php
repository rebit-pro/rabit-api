<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request\Attribute;

/** Параметр request DTO получает IP клиента, вычисленный за доверенными прокси; из body/query его задать нельзя. */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class ClientAddress {}
