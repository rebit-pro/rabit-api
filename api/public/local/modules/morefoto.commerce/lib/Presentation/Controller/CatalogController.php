<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Catalog\Service\AuthorizedCatalog;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\MalformedCatalogJsonException;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Morefoto\Commerce\Domain\Catalog\Exception\ProductNotFoundException;
use Morefoto\Commerce\Infrastructure\Adapter\CatalogTokenResolver;
use Morefoto\Commerce\Presentation\Mapper\CatalogResponseMapper;
use Morefoto\Commerce\Presentation\Request\CatalogRequestFactory;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Shared\Exception\HttpException;

final class CatalogController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly AuthorizedCatalog $catalog,
        private readonly CatalogRequestFactory $requests,
        private readonly CatalogResponseMapper $responses,
        private readonly CatalogTokenResolver $tokens,
    ) {
        parent::__construct();
    }

    public function listAction(): ControllerJson
    {
        return $this->respond(function(): ControllerJson {
            $input = $this->requests->list($this->getRequest()->getQueryList()->toArray(), (string)$this->getRequest()::getInput());
            $output = $this->catalog->list($this->getAuthUserId(), $this->token(), $input->input);

            return $this->json($this->responses->data($output), ['page' => $output->page, 'pageSize' => $output->pageSize, 'total' => $output->total]);
        });
    }

    public function createAction(): ControllerJson
    {
        return $this->respond(function(): ControllerJson {
            $input = $this->requests->create((string)$this->getRequest()::getInput(), $this->getRequest()->getHeader('Content-Type') ?? '', $this->getRequest()->getHeader('Idempotency-Key') ?? '', $this->getRequest()->getQueryList()->toArray());
            $output = $this->catalog->create($this->getAuthUserId(), $this->token(), $input->idempotencyKey, $input->input);

            return $this->json(['id' => $output->id, 'revision' => $output->revision])->setStatus(201)->addHeader('Location', '/api/v1/catalog/products/' . $output->id);
        });
    }

    public function updateAction(string $product_id): ControllerJson
    {
        return $this->respond(function() use ($product_id): ControllerJson {
            $input = $this->requests->update((string)$this->getRequest()::getInput(), $this->getRequest()->getHeader('Content-Type') ?? '', $this->getRequest()->getHeader('Idempotency-Key') ?? '', $product_id, $this->getRequest()->getQueryList()->toArray());
            $output = $this->catalog->update($this->getAuthUserId(), $this->token(), $input->idempotencyKey, $input->input);

            return $this->json(['id' => $output->id, 'revision' => $output->revision]);
        });
    }

    public function configureActions(): array
    {
        $filters = [new BearerTokenFilter($this->tokens), new LoggerFilter()];

        return ['list' => ['prefilters' => $filters], 'create' => ['prefilters' => $filters], 'update' => ['prefilters' => $filters]];
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
            $exception instanceof InvalidProductException => [422, 'VALIDATION_FAILED', $exception->getMessage()],
            $exception instanceof ProductNotFoundException => [404, 'NOT_FOUND', 'Product not found.'],
            $exception instanceof CatalogRevisionConflictException => [409, 'REVISION_CONFLICT', 'Catalog changed; reload before updating.'],
            $exception instanceof IdempotencyConflictException => [409, 'IDEMPOTENCY_CONFLICT', 'Idempotency key was already used with a different request.'],
            $exception instanceof CatalogStorageException => [503, 'CATALOG_UNAVAILABLE', 'Catalogue service is unavailable.'],
            $exception instanceof CatalogAccessException, $exception instanceof HttpException => match ($exception->getCode()) {
                401 => [401, 'UNAUTHORIZED', 'Unauthorized'],
                403 => [403, 'FORBIDDEN', 'Catalogue access is forbidden.'],
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
