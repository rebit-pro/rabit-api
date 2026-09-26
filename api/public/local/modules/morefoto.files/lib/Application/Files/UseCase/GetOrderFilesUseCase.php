<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;

/** FIL-01: показывает покупателю по личному ключу, действует ли право на электронные файлы, до какого момента и какие файлы доступны. */
final readonly class GetOrderFilesUseCase
{
    public function __construct(private OrderFileAccess $access) {}

    public function execute(?string $orderKey): FileAccessOutputDto
    {
        return $this->access->byKey($orderKey);
    }
}
