<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Gallery\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class GalleryResultDto implements ResponseDtoInterface
{
    /** @param list<array{code:string,photos:list<array{id:string,assignmentId:string,code:string,thumbSrc:string,previewSrc:string,width:int,height:int}>}> $children */
    public function __construct(
        public string $groupId,
        public string $institutionName,
        public string $groupName,
        public string $shootName,
        public string $audience,
        public string $state,
        public ?string $sentAt,
        public ?string $closesAt,
        public string $referenceNow,
        public array $children,
        public string $curator,
        public string $delivery,
    ) {}
}
