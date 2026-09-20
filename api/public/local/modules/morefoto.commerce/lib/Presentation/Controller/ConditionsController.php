<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Morefoto\Commerce\Application\Conditions\Service\AuthorizedConditions;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\MalformedCatalogJsonException;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsRevisionConflictException;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Infrastructure\Adapter\CatalogTokenResolver;
use Morefoto\Commerce\Presentation\Mapper\ConditionsResponseMapper;
use Morefoto\Commerce\Presentation\Request\ConditionsRequestFactory;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Rebit\Share\Shared\Exception\HttpException;

final class ConditionsController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly AuthorizedConditions $conditions,
        private readonly ConditionsRequestFactory $requests,
        private readonly ConditionsResponseMapper $responses,
        private readonly CatalogTokenResolver $tokens,
    ) {
        parent::__construct();
    }

    public function globalAction(): ControllerJson
    {
        return $this->respond(function(): ControllerJson {
            $this->requests->read((string)$this->getRequest()::getInput(), $this->queryParameters());
            $output = $this->conditions->getGlobal($this->getAuthUserId(), $this->token());

            return $this->json($this->responses->data($output, false));
        });
    }

    public function saveGlobalAction(): ControllerJson
    {
        return $this->respond(function(): ControllerJson {
            $request = $this->requests->save((string)$this->getRequest()::getInput(), $this->getRequest()->getHeader('Content-Type') ?? '', $this->getRequest()->getHeader('Idempotency-Key') ?? '', $this->queryParameters(), false);
            $output = $this->conditions->saveGlobal($this->getAuthUserId(), $this->token(), $request->idempotencyKey, $request->input);

            return $this->json(['revision' => $output->revision]);
        });
    }

    public function groupAction(string $group_id): ControllerJson
    {
        return $this->respond(function() use ($group_id): ControllerJson {
            $this->requests->read((string)$this->getRequest()::getInput(), $this->queryParameters());
            $output = $this->conditions->getGroup($this->getAuthUserId(), $this->token(), $group_id);

            return $this->json($this->responses->data($output, true));
        });
    }

    public function saveGroupAction(string $group_id): ControllerJson
    {
        return $this->respond(function() use ($group_id): ControllerJson {
            $request = $this->requests->save((string)$this->getRequest()::getInput(), $this->getRequest()->getHeader('Content-Type') ?? '', $this->getRequest()->getHeader('Idempotency-Key') ?? '', $this->queryParameters(), true);
            $output = $this->conditions->saveGroup($this->getAuthUserId(), $this->token(), $group_id, $request->idempotencyKey, $request->input);

            return $this->json(['revision' => $output->revision, 'conditionsRevision' => $output->conditionsRevision]);
        });
    }

    public function configureActions(): array
    {
        $filters = [new BearerTokenFilter($this->tokens), new LoggerFilter()];

        return ['global' => ['prefilters' => $filters], 'saveGlobal' => ['prefilters' => $filters], 'group' => ['prefilters' => $filters], 'saveGroup' => ['prefilters' => $filters]];
    }

    /** @return array<string, mixed> */
    private function queryParameters(): array
    {
        $query = [];
        parse_str((string)parse_url((string)$this->getRequest()->getRequestUri(), PHP_URL_QUERY), $query);

        return $query;
    }

    private function token(): string
    {
        return substr($this->getRequest()->getHeader('Authorization') ?? '', 7);
    }

    /** @param callable(): ControllerJson $operation */
    private function respond(callable $operation): ControllerJson
    {
        return $operation()->addHeader('Cache-Control', 'no-store');
    }

    public function finalizeResponse(Response|string $response): void
    {
        parent::finalizeResponse($response);
        if ($response instanceof HttpResponse) {
            $response->addHeader('Cache-Control', 'no-store');
        }
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $exception = $this->thrownException;
        [$status, $code, $message] = match (true) {
            $exception instanceof MalformedCatalogJsonException => [400, 'MALFORMED_JSON', 'Malformed JSON body.'],
            $exception instanceof InvalidConditionsException, $exception instanceof InvalidProductException => [422, 'VALIDATION_FAILED', $exception->getMessage()],
            $exception instanceof CatalogRevisionConflictException, $exception instanceof ConditionsRevisionConflictException => [409, 'REVISION_CONFLICT', 'Sales conditions changed; reload before saving.'],
            $exception instanceof IdempotencyConflictException => [409, 'IDEMPOTENCY_CONFLICT', 'Idempotency key was already used with a different request.'],
            $exception instanceof CatalogStorageException, $exception instanceof ConditionsStorageException => [503, 'CONDITIONS_UNAVAILABLE', 'Sales conditions service is unavailable.'],
            $exception instanceof CatalogAccessException, $exception instanceof HttpException => match ($exception->getCode()) {
                401 => [401, 'UNAUTHORIZED', 'Unauthorized'],
                403 => [403, 'FORBIDDEN', 'Sales condition access is forbidden.'],
                404 => [404, 'NOT_FOUND', 'Group not found.'],
                503 => [503, 'ACCESS_UNAVAILABLE', 'Access service is unavailable.'],
                default => [500, 'INTERNAL_ERROR', 'Server error.'],
            },
            default => [500, 'INTERNAL_ERROR', 'Server error.'],
        };

        return (new ControllerJson(CommonSerializer::createDefault(), [
            'error' => ['code' => $code, 'message' => $message],
            'meta' => ['requestId' => RequestIdGenerator::getRequestId()],
        ]))->setStatus($status);
    }
}
