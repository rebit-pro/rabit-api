<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;

final readonly class PhotoPageResultDto implements ResultDtoInterface
{
    /**
     * @param list<PhotoOutputDto>                   $items
     * @param list<MediaGroupOutputDto>              $groups
     * @param array<string,string>                   $covers
     * @param array{page:int,pageSize:int,total:int} $meta
     * @param array{
     *     byStatus: array{processing: int, ready: int, failed: int, duplicate: int},
     *     unassigned: int,
     * } $stats
     */
    public function __construct(
        public array $items,
        public array $groups,
        public array $covers,
        public int $revision,
        public array $meta,
        public ?PhotoGroupSummaryResultDto $summary,
        public array $stats,
    ) {}
}
