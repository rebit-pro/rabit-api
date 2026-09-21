<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Storefront\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;

#[JsonBody]
#[StrictRequest]
final readonly class CreateQuoteRequestDto implements RequestDtoInterface
{
    /** @param list<QuoteLineRequestDto> $lines */
    public function __construct(
        #[RouteParameter(name: 'gallery_token', pattern: '/^[a-f0-9]{64}$/D', errorCode: 'GALLERY_NOT_FOUND', errorStatus: 404)]
        public string $token,
        /** @var QuoteLineRequestDto[] */
        public array $lines,
    ) {}
}
