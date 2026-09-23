<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Infrastructure\Helpers\RequestHelper;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * Мапит результаты запроса в DTO c интерфейсом RequestDtoInterface
 *
 * @template T of RequestDtoInterface
 */
final readonly class RequestToDtoMapper implements RequestMapperInterface
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    public function support(string $className): bool
    {
        return is_subclass_of($className, RequestDtoInterface::class);
    }

    /**
     * @throws ValidationHttpException
     * @throws HttpException|\ReflectionException
     */
    public function map(string $className): object
    {
        $reflection = new \ReflectionClass($className);
        $jsonBody = $reflection->getAttributes(JsonBody::class)[0] ?? null;
        $strict = [] !== $reflection->getAttributes(StrictRequest::class);
        $requestData = null === $jsonBody
            ? ($strict ? RequestHelper::collectQueryValues($this->request) : RequestHelper::collectRequestValues($this->request))
            : RequestHelper::collectJsonRequestValues(
                $this->request,
                $jsonBody->newInstance()->maxBytes,
            );

        $requestData = (new RequestTechnicalValues($this->request))->append($reflection, $requestData);
        if ($strict) {
            $requestData = StrictRequestValues::normalize($requestData, $className, null !== $jsonBody);
        }

        return ArrayToDtoMapper::map($requestData, $className);
    }
}
