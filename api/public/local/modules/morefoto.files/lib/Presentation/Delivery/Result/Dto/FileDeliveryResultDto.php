<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Delivery\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class FileDeliveryResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public string $recipient,
        public string $createdAt,
        public ?string $acceptedAt,
        public int $letters,
        public int $acceptedLetters,
        public int $photos,
        public int $linkOnly,
    ) {}
}
