<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class GlobalConditionsRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RequestHeader('Authorization')]
        public string $authorization,
    ) {}
}
