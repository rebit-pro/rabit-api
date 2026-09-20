<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Morefoto\Media\Application\Photo\UseCase\AssignPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\GetPhotoUseCase;
use Morefoto\Media\Application\Photo\UseCase\ListPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\SetGroupCoverUseCase;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Presentation\Request\MediaRequestFactory;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Rebit\Share\Shared\Exception\HttpException;

final class MediaController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly ListPhotosUseCase $list,
        private readonly UploadPhotoUseCase $upload,
        private readonly GetPhotoUseCase $detail,
        private readonly AssignPhotosUseCase $assignments,
        private readonly SetGroupCoverUseCase $covers,
        private readonly MediaRequestFactory $requests,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function listAction(): ControllerJson
    {
        return $this->json($this->list->execute(
            $this->getAuthUserId(),
            $this->requests->routeId('shoot_id'),
            $this->requests->listing($this->getRequest()),
        ));
    }

    public function uploadAction(): ControllerJson
    {
        $input = $this->requests->upload($this->getRequest());

        return $this->json($this->upload->execute(
            $this->getAuthUserId(),
            $this->requests->routeId('shoot_id'),
            $input['groupId'],
            $input['tmpName'],
            $input['filename'],
            $input['bytes'],
            $input['fingerprint'],
        ))->setStatus(202);
    }

    public function detailAction(): ControllerJson
    {
        return $this->json($this->detail->execute($this->getAuthUserId(), $this->requests->routeId('photo_id')));
    }

    public function assignmentAction(): ControllerJson
    {
        $request = $this->requests->assignment($this->getRequest());

        return $this->json($this->assignments->execute(
            $this->getAuthUserId(),
            $this->requests->routeId('group_id'),
            $request['key'],
            $request['input'],
        ));
    }

    public function coverAction(): ControllerJson
    {
        $request = $this->requests->cover($this->getRequest());

        return $this->json($this->covers->execute(
            $this->getAuthUserId(),
            $this->requests->routeId('group_id'),
            $request['key'],
            $request['input'],
        ));
    }

    public function configureActions(): array
    {
        $filters = ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]];

        return [
            'list' => $filters,
            'upload' => $filters,
            'detail' => $filters,
            'assignment' => $filters,
            'cover' => $filters,
        ];
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $error = $this->thrownException;
        $status = match (true) {
            $error instanceof HttpException && in_array($error->getCode(), [400, 401, 403, 404, 409, 413, 422], true) => $error->getCode(),
            default => 503,
        };
        $code = $error instanceof HttpException && 1 === preg_match('/^[A-Z_]+$/D', $error->getMessage())
            ? $error->getMessage()
            : (503 === $status ? 'SERVICE_UNAVAILABLE' : 'MEDIA_REQUEST_FAILED');

        return (new ControllerJson(CommonSerializer::createDefault(), [
            'error' => ['code' => $code, 'message' => $code],
            'meta' => ['requestId' => RequestIdGenerator::getRequestId()],
        ]))->setStatus($status);
    }

    public function finalizeResponse(Response|string $response): void
    {
        parent::finalizeResponse($response);
        if ($response instanceof HttpResponse) {
            $response->addHeader('Cache-Control', 'no-store');
        }
    }
}
