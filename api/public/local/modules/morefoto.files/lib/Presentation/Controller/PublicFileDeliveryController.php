<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Controller;

use Morefoto\Files\Application\Delivery\UseCase\GetFileDeliveryUseCase;
use Morefoto\Files\Application\Delivery\UseCase\GetOrderEmailUseCase;
use Morefoto\Files\Application\Delivery\UseCase\SendOrderEmailAgainUseCase;
use Morefoto\Files\Presentation\Delivery\DeliveryInputMapper;
use Morefoto\Files\Presentation\Delivery\DeliveryResultMapper;
use Morefoto\Files\Presentation\Delivery\Request\Dto\CreateFileDeliveryRequestDto;
use Morefoto\Files\Presentation\Delivery\Request\Dto\FileDeliveryRequestDto;
use Morefoto\Files\Presentation\Delivery\Request\Dto\OrderEmailRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class PublicFileDeliveryController extends PrivateApiJsonController
{
    public function __construct(
        private readonly GetOrderEmailUseCase $summary,
        private readonly SendOrderEmailAgainUseCase $send,
        private readonly GetFileDeliveryUseCase $delivery,
        private readonly DeliveryInputMapper $input,
        private readonly DeliveryResultMapper $result,
    ) {
        parent::__construct();
    }

    public function summaryAction(OrderEmailRequestDto $request): ControllerJson
    {
        return $this->json($this->result->summary($this->summary->execute($request->orderKey)));
    }

    public function createAction(CreateFileDeliveryRequestDto $request): ControllerJson
    {
        return $this->acceptedJson($this->result->delivery($this->send->execute($request->orderKey, $this->input->key($request))));
    }

    public function deliveryAction(FileDeliveryRequestDto $request): ControllerJson
    {
        return $this->json($this->result->delivery($this->delivery->execute($request->orderKey, $request->deliveryId)));
    }
}
