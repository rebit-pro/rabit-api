<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Institution\UseCase\ListVisibleInstitutionsUseCase;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Domain\Institution\Exception\InvalidInstitutionException;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionVersionConflictException;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionNotFoundException;
use Morefoto\Organization\Presentation\Request\InstitutionRequestFactory;
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

final class InstitutionController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly ListVisibleInstitutionsUseCase $listing,
        private readonly SaveInstitutionUseCase $save,
        private readonly InstitutionRequestFactory $requests,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function listAction(): ControllerJson
    {
        $result = $this->listing->execute($this->getAuthUserId(), $this->bearer(), $this->requests->listing($this->getRequest()));

        return $this->json(
            ['items' => $result->items, 'assignmentSignature' => $result->assignmentSignature],
            ['page' => $result->page, 'pageSize' => $result->pageSize, 'total' => $result->total, 'totalPages' => (int)ceil($result->total / $result->pageSize)],
        );
    }

    public function createAction(): ControllerJson
    {
        $dto = $this->requests->mutation($this->getRequest(), true);

        $result = $this->save->execute($this->getAuthUserId(), $this->bearer(), null, $dto->input);
        $response = $this->json($result)->setStatus(201);
        $response->addHeader('Location', '/api/v1/institutions/' . $result->id);

        return $response;
    }

    public function updateAction(string $institution_id): ControllerJson
    {
        $dto = $this->requests->mutation($this->getRequest(), false);

        return $this->json($this->save->execute($this->getAuthUserId(), $this->bearer(), new InstitutionId($institution_id), $dto->input));
    }

    public function configureActions(): array
    {
        $filters = ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]];

        return ['list' => $filters, 'create' => $filters, 'update' => $filters];
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $error = $this->thrownException;
        $status = match (true) {
            $error instanceof InvalidInstitutionException, $error instanceof \InvalidArgumentException => 422,
            $error instanceof InstitutionVersionConflictException => 409,
            $error instanceof InstitutionNotFoundException => 404,
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
