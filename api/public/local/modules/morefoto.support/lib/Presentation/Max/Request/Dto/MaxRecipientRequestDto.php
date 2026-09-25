<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class MaxRecipientRequestDto
{
    public function __construct(
        #[SerializedName('chat_id')]
        public ?int $chatId = null,
        #[SerializedName('chat_type')]
        public ?string $chatType = null,
    ) {}
}
