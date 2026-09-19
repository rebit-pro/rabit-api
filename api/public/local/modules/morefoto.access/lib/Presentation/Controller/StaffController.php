<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Morefoto\Access\Application\Staff\UseCase\SaveStaffUseCase;
use Morefoto\Access\Application\Staff\UseCase\StaffDirectoryUseCase;
use Morefoto\Access\Presentation\Request\StaffRequestFactory;
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

final class StaffController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly StaffDirectoryUseCase $directory,
        private readonly SaveStaffUseCase $save,
        private readonly StaffRequestFactory $requests,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function listAction(): ControllerJson
    {
        $result = $this->directory->list($this->getAuthUserId(), $this->requests->listing($this->getRequest()));

        return $this->json(['items' => $result->items], [
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'total' => $result->total,
            'totalPages' => (int)ceil($result->total / $result->pageSize),
        ]);
    }

    public function getAction(string $user_id): ControllerJson
    {
        return $this->json($this->directory->get($this->getAuthUserId(), $this->userId($user_id)));
    }

    public function optionsAction(): ControllerJson
    {
        return $this->json($this->directory->options($this->getAuthUserId()));
    }

    public function createAction(): ControllerJson
    {
        $result = $this->save->execute($this->getAuthUserId(), $this->bearer(), null, $this->requests->mutation($this->getRequest(), true));
        $response = $this->json($result)->setStatus(201);
        $response->addHeader('Location', '/api/v1/users/' . $result->id);

        return $response;
    }

    public function updateAction(string $user_id): ControllerJson
    {
        return $this->json($this->save->execute($this->getAuthUserId(), $this->bearer(), $this->userId($user_id), $this->requests->mutation($this->getRequest(), false)));
    }

    public function configureActions(): array
    {
        $filters = ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]];

        return ['list' => $filters, 'get' => $filters, 'options' => $filters, 'create' => $filters, 'update' => $filters];
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $error = $this->thrownException;
        $status = $error instanceof HttpException && in_array($error->getCode(), [400, 401, 403, 404, 409, 422], true) ? $error->getCode() : ($error instanceof \InvalidArgumentException ? 422 : 503);
        $default = match ($status) {
            400 => 'INVALID_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            default => 'SERVICE_UNAVAILABLE',
        };
        $code = $error instanceof HttpException && 1 === preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : $default;

        return (new ControllerJson(CommonSerializer::createDefault(), [
            'error' => ['code' => $code, 'message' => $default],
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

    private function bearer(): string
    {
        return substr((string)$this->getRequest()->getHeader('Authorization'), 7);
    }

    private function userId(string $value): int
    {
        if (1 !== preg_match('/^[1-9][0-9]{0,9}$/D', $value) || 2147483647 < (int)$value) {
            throw new HttpException('STAFF_NOT_FOUND', 404);
        }

        return (int)$value;
    }
}
