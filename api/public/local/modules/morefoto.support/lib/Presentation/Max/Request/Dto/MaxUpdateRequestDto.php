<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Symfony\Component\Serializer\Annotation\SerializedName;

/** Update MAX (schema 1a4a502f): лишние поля события отбрасываются, так как MAX добавляет их без версии. */
#[JsonBody(maxBytes: 65536)]
final readonly class MaxUpdateRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[SerializedName('update_type')]
        public string $updateType,
        #[SerializedName('chat_id')]
        public ?int $chatId = null,
        public ?MaxMessageRequestDto $message = null,
    ) {}
}
