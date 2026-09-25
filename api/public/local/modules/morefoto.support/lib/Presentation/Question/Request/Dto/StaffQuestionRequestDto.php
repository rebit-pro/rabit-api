<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffQuestionRequestDto implements RequestDtoInterface
{
    public function __construct() {}
}
