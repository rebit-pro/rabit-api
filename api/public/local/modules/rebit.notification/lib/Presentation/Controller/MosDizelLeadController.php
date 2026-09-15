<?php

declare(strict_types=1);

namespace Rebit\Notification\Presentation\Controller;

use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Application\Lead\UseCase\SubmitMosDizelLeadUseCase;
use Rebit\Notification\Infrastructure\Lead\UploadedFileValidator;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Приём заявок mos-dizel.ru с прямой доставкой только по электронной почте.
 */
final class MosDizelLeadController extends BaseJsonController
{
    public function __construct(
        private readonly SubmitMosDizelLeadUseCase $submitLeadUseCase,
        private readonly UploadedFileValidator $uploadedFileValidator,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/v1/lead/mos-dizel
     *
     * @throws HttpException
     * @throws ValidationHttpException
     */
    public function submitAction(SubmitLeadRequestDto $dto): ControllerJson
    {
        $attachment = $this->uploadedFileValidator->validate($this->request->getFile('file'));

        return $this->json($this->submitLeadUseCase->execute($dto, $attachment));
    }
}
