<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;
use Symfony\Component\Validator\Constraints as Assert;

#[StrictRequest]
final readonly class SalesReportRequestDto implements RequestDtoInterface
{
    public function __construct(
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $groupId = null,
        #[Assert\Choice(choices: ['all', 'ordered'])]
        public string $childrenView = 'all',
    ) {}
}
