<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Morefoto\Handoff\Application\Request\UseCase\ClarifyStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\ListStaffRequestsUseCase;
use Morefoto\Handoff\Application\Request\UseCase\SaveStaffRequestUseCase;
use Morefoto\Handoff\Presentation\Request\StaffRequestFactory;
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

final class StaffRequestController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly ListStaffRequestsUseCase $list,
        private readonly GetStaffRequestUseCase $detail,
        private readonly SaveStaffRequestUseCase $save,
        private readonly ClarifyStaffRequestUseCase $clarify,
        private readonly StaffRequestFactory $requests,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function listAction(): ControllerJson
    {
        $result = $this->list->execute($this->getAuthUserId(), $this->requests->listing($this->getRequest()));

        return $this->json(['items' => $result['items'], 'scope' => $result['scope']], $result['meta']);
    }

    public function detailAction(): ControllerJson
    {
        return $this->json($this->detail->execute($this->getAuthUserId(), $this->requests->routeId()));
    }

    public function createAction(): ControllerJson
    {
        $request = $this->requests->mutation($this->getRequest(), true);
        $result = $this->save->execute($this->getAuthUserId(), null, $request['key'], $request['input']);
        $response = $this->json($result)->setStatus(201);
        $response->addHeader('Location', '/api/v1/staff-requests/' . $result->id);

        return $response;
    }

    public function updateAction(): ControllerJson
    {
        $request = $this->requests->mutation($this->getRequest(), false);

        return $this->json($this->save->execute($this->getAuthUserId(), $this->requests->routeId(), $request['key'], $request['input']));
    }

    public function clarificationAction(): ControllerJson
    {
        $request = $this->requests->clarification($this->getRequest());

        return $this->json($this->clarify->execute($this->getAuthUserId(), $this->requests->routeId(), $request['key'], $request['input']));
    }

    public function configureActions(): array
    {
        $filters = ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]];

        return ['list' => $filters, 'detail' => $filters, 'create' => $filters, 'update' => $filters, 'clarification' => $filters];
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $error = $this->thrownException;
        $status = $error instanceof HttpException && in_array($error->getCode(), [400, 401, 403, 404, 409, 413, 422, 503], true) ? $error->getCode() : 503;
        $code = $error instanceof HttpException && 1 === preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'HANDOFF_UNAVAILABLE';

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
