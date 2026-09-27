<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Order\UseCase\DeleteOrderUseCase;
use Morefoto\Commerce\Presentation\Order\Dto\DeleteOrderRequestDto;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Removal of an order without money by the organizer (#92 DEC-02). */
final class StaffOrderRemovalController extends AuthenticatedApiJsonController
{
    public function __construct(private readonly DeleteOrderUseCase $delete)
    {
        parent::__construct();
    }

    public function deleteAction(DeleteOrderRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $request->orderId);

        return $this->noContent();
    }
}
