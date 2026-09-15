<?php

declare(strict_types=1);

namespace Rebit\Notification\Presentation\Controller;

use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Application\Lead\UseCase\SubmitLeadUseCase;
use Rebit\Notification\Infrastructure\Lead\UploadedFileValidator;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Приём заявок с формы сайта и доставка в Telegram.
 *
 * Эндпоинт публичный (без авторизации). CORS и preflight (OPTIONS)
 * обрабатывает nginx (см. docker/common/nginx/conf.d), поэтому контроллер
 * заголовки CORS не добавляет — иначе они продублируются.
 *
 * Форма отправляет multipart/form-data: скалярные поля мапятся в DTO
 * автоматически (RequestToDtoMapper читает getPostList()), а опциональный
 * файл ТЗ контроллер достаёт из запроса отдельно и проверяет на сервере.
 */
final class LeadController extends BaseJsonController
{
    public function __construct(
        private readonly SubmitLeadUseCase $submitLeadUseCase,
        private readonly SubmitLeadUseCase $submitMosDizelLeadUseCase,
        private readonly UploadedFileValidator $uploadedFileValidator,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/v1/lead
     *
     * @throws HttpException
     * @throws ValidationHttpException
     */
    public function submitAction(SubmitLeadRequestDto $dto): ControllerJson
    {
        return $this->submit($dto, $this->submitLeadUseCase);
    }

    /**
     * POST /api/v1/lead/mos-dizel
     *
     * @throws HttpException
     * @throws ValidationHttpException
     */
    public function submitMosDizelAction(SubmitLeadRequestDto $dto): ControllerJson
    {
        return $this->submit($dto, $this->submitMosDizelLeadUseCase);
    }

    /**
     * @throws HttpException
     * @throws ValidationHttpException
     */
    private function submit(SubmitLeadRequestDto $dto, SubmitLeadUseCase $useCase): ControllerJson
    {
        // Файл не кладём в DTO: достаём из запроса и валидируем по содержимому.
        // Файл не сохраняется на диск — работаем только с PHP-temp загрузки.
        $attachment = $this->uploadedFileValidator->validate($this->request->getFile('file'));

        return $this->json($useCase->execute($dto, $attachment));
    }
}
