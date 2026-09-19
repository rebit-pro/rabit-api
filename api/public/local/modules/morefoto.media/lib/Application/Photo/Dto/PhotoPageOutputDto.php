<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class PhotoPageOutputDto implements ResponseDtoInterface
{
    /**
     * @param list<PhotoOutputDto>                   $items
     * @param list<MediaGroupOutputDto>              $groups
     * @param array<string,string>                   $covers
     * @param array{page:int,pageSize:int,total:int} $meta
     */
    public function __construct(
        public array $items,
        public array $groups,
        public array $covers,
        public int $revision,
        public array $meta,
    ) {}
}
