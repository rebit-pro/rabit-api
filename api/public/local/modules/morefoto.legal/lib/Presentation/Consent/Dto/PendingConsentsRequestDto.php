<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Consent\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class PendingConsentsRequestDto implements RequestDtoInterface
{
    public function __construct() {}
}
