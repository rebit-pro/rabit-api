<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Order\UseCase\CreateOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\GetBuyerOrderUseCase;
use Morefoto\Commerce\Presentation\Order\Dto\CreateOrderRequestDto;
use Morefoto\Commerce\Presentation\Order\Dto\CurrentOrderRequestDto;
use Morefoto\Commerce\Presentation\Order\OrderInputMapper;
use Morefoto\Commerce\Presentation\Order\OrderResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class OrderController extends PrivateApiJsonController
{
    public function __construct(
        private readonly CreateOrderUseCase $create,
        private readonly GetBuyerOrderUseCase $current,
        private readonly OrderInputMapper $input,
        private readonly OrderResultMapper $result,
    ) {
        parent::__construct();
    }

    public function createAction(CreateOrderRequestDto $request): ControllerJson
    {
        $output = $this->create->execute($request->token, $this->input->key($request), $this->input->create($request));

        return $this->createdJson($this->result->created($output), $this->result->location());
    }

    public function currentAction(CurrentOrderRequestDto $request): ControllerJson
    {
        return $this->json($this->result->buyerOrder($this->current->execute($request->orderKey)));
    }
}
