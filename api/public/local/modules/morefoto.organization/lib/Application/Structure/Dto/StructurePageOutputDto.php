<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class StructurePageOutputDto implements ResponseDtoInterface
{
    /** @param list<GroupOutputDto|ShootOutputDto> $items
     * @param array{page:int,pageSize:int,total:int,totalPages:int} $meta
     */
    public function __construct(public array $items, public array $meta) {}
}
