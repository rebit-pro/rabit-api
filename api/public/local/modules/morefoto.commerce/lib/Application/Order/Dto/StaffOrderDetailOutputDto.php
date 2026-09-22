<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;

final readonly class StaffOrderDetailOutputDto
{
    /** @param list<GalleryAssignmentOutputDto> $correctionPhotos */
    public function __construct(public OrderOutputDto $order, public OrderPeriodOutputDto $period, public array $correctionPhotos) {}
}
