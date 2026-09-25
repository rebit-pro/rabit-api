<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class AskQuestionRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'gallery_token', pattern: '/^[a-f0-9]{64}$/D', errorCode: 'GALLERY_NOT_FOUND', errorStatus: 404)]
        public string $token,
        public string $name,
        public string $message,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
