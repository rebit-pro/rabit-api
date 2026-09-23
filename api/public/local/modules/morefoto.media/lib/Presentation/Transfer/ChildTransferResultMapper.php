<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Transfer;

use Morefoto\Media\Application\Transfer\Dto\TransferChildOutputDto;
use Morefoto\Media\Presentation\Transfer\Dto\ChildTransferResultDto;

final readonly class ChildTransferResultMapper
{
    public function transfer(TransferChildOutputDto $output): ChildTransferResultDto
    {
        return new ChildTransferResultDto(
            photoIds: $output->photoIds,
            fromGroupId: $output->fromGroupId,
            toGroupId: $output->toGroupId,
            childCode: $output->childCode,
            revision: $output->revision,
        );
    }
}
