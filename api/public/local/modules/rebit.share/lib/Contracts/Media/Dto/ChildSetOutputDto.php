<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class ChildSetOutputDto
{
    /**
     * @param list<ChildSetPhotoOutputDto> $photos           все назначенные кадры по номеру
     * @param list<string>                 $sharedPhotoCodes коды кадров, назначенных ещё и ребёнку вне переносимых
     */
    public function __construct(
        public int $childId,
        public string $childPublicId,
        public int $groupId,
        public string $code,
        public array $photos,
        public array $sharedPhotoCodes,
    ) {}
}
