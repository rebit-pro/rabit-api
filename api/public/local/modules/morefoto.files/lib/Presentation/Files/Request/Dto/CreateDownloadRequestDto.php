<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 32768)]
#[StrictRequest]
final readonly class CreateDownloadRequestDto implements RequestDtoInterface
{
    /** @param null|list<mixed> $photoIds элементы проверяет FilesInputMapper, чтобы вернуть код INVALID_DOWNLOAD */
    public function __construct(
        public string $kind,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
        /** @var mixed[] nullable из нативного типа: парсер метаданных DTO читает только форму тип[] */
        public ?array $photoIds = null,
        #[RequestHeader('X-Order-Key', required: false)]
        public ?string $orderKey = null,
    ) {}
}
