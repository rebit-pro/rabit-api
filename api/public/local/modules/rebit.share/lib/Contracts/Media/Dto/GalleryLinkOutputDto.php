<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class GalleryLinkOutputDto
{
    /** @param null|string $alias короткий псевдоним ссылки (F3); null только у ключа, которому его ещё не выдали */
    public function __construct(
        public string $token,
        public \DateTimeImmutable $issuedAt,
        public ?string $alias = null,
    ) {}
}
