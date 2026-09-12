<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Structure\UseCase\ListShootsUseCase;
use Morefoto\Organization\Application\Structure\UseCase\GetShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Domain\Structure\Exception\StructureVersionConflictException;
use Morefoto\Organization\Infrastructure\Routing\StructureRouteParameters;
use Morefoto\Organization\Presentation\Request\StructureRequestFactory;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Bitrix\Main\Response;
use Bitrix\Main\HttpResponse;

final class StructureController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly ListShootsUseCase $list,
        private readonly GetShootUseCase $detail,
        private readonly SaveShootUseCase $saveShoot,
        private readonly SaveGroupUseCase $saveGroup,
        private readonly StructureRequestFactory $requests,
        private readonly StructureRouteParameters $route,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function listShootsAction(): ControllerJson
    {
        $result = $this->list->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('institution_id'), $this->requests->listing($this->getRequest()));

        return $this->json(['items' => $result->items], $result->meta);
    }

    public function getShootAction(): ControllerJson
    {
        return $this->json($this->detail->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('shoot_id'), $this->requests->listing($this->getRequest())));
    }

    public function createShootAction(): ControllerJson
    {
        $result = $this->saveShoot->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('institution_id'), true, $this->requests->shoot($this->getRequest(), true)->input);
        $response = $this->json($result)->setStatus(201);
        $response->addHeader('Location', '/api/v1/shoots/' . $result->id);

        return $response;
    }

    public function updateShootAction(): ControllerJson
    {
        return $this->json($this->saveShoot->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('shoot_id'), false, $this->requests->shoot($this->getRequest(), false)->input));
    }

    public function createGroupAction(): ControllerJson
    {
        return $this->json($this->saveGroup->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('shoot_id'), true, $this->requests->group($this->getRequest(), true)->input))->setStatus(201);
    }

    public function updateGroupAction(): ControllerJson
    {
        return $this->json($this->saveGroup->execute($this->getAuthUserId(), $this->bearer(), $this->route->id('group_id'), false, $this->requests->group($this->getRequest(), false)->input));
    }

    public function configureActions(): array
    {
        $filters = ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]];

        return ['listShoots' => $filters, 'getShoot' => $filters, 'createShoot' => $filters, 'updateShoot' => $filters, 'createGroup' => $filters, 'updateGroup' => $filters];
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $error = $this->thrownException;
        $status = match (true) {
            $error instanceof \InvalidArgumentException => 422,
            $error instanceof StructureVersionConflictException => 409,
            $error instanceof HttpException && in_array($error->getCode(), [400, 401, 403, 404, 409, 422], true) => $error->getCode(),
            default => 503,
        };
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
}
