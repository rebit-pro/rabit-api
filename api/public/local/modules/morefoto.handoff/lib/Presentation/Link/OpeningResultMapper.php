<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link;

use Morefoto\Handoff\Application\Link\Dto\OpeningCommandOutputDto;
use Morefoto\Handoff\Application\Link\Dto\ManualDeliveryOutputDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\ManualDeliveryResultDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\OpeningResultDto;

final readonly class OpeningResultMapper
{
    public function map(OpeningCommandOutputDto $output): OpeningResultDto
    {
        return new OpeningResultDto($output->revision, $output->status, $output->openAt);
    }

    public function manual(ManualDeliveryOutputDto $output): ManualDeliveryResultDto
    {
        return new ManualDeliveryResultDto($output->revision, $output->manualSentAt);
    }
}
