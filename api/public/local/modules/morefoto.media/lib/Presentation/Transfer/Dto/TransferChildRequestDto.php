<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Transfer\Dto;

use Morefoto\Media\Presentation\Transfer\ChildTransferInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class TransferChildRequestDto implements RequestDtoInterface
{
    /** @param list<string> $expectedPhotoIds */
    public function __construct(
        public string $fromGroupId,
        public string $toGroupId,
        public string $childCode,
        public string $targetCode,
        /** @var string[] */
        public array $expectedPhotoIds,
        public int $revision,
        #[RouteParameter(
            name: 'shoot_id',
            pattern: ChildTransferInputMapper::UUID_PATTERN,
        )]
        public string $shootId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
