<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Institution\Result\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class DigitalArchiveListResultDto implements ResponseDtoInterface
{
    /** @param list<DigitalArchiveResultDto> $items */
    public function __construct(public bool $canCreate, public array $items) {}
}
