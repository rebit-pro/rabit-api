<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
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

        $requestData = $this->appendTechnicalValues($reflection, $requestData);
        if ($strict) {
            $requestData = StrictRequestValues::normalize($requestData, $className, null !== $jsonBody);
        }

        return ArrayToDtoMapper::map($requestData, $className);
    }

    /**
     * Дополняет данные DTO значениями из HTTP-заголовков и текущего маршрута по атрибутам конструктора.
     * Запрещает подменять эти поля через body/query и проверяет формат параметров маршрута до гидрации DTO.
     *
     * @param \ReflectionClass<object> $reflection
     * @param array<string, mixed> $requestData
     *
     * @return array<string, mixed>
     * @throws HttpException
     */
    private function appendTechnicalValues(\ReflectionClass $reflection, array $requestData): array
    {
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            return $requestData;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $header = $parameter->getAttributes(RequestHeader::class)[0] ?? null;
            $route = $parameter->getAttributes(RouteParameter::class)[0] ?? null;
            if ((null !== $header || null !== $route) && array_key_exists($parameter->getName(), $requestData)) {
                throw new HttpException('UNKNOWN_FIELD', 422);
            }
            if (null !== $header) {
                $attribute = $header->newInstance();
                $value = $this->request->getHeader($attribute->name);
                $requestData[$parameter->getName()] = $attribute->required ? (string)$value : $value;
            }

            if (null === $route) {
                continue;
            }

            $attribute = $route->newInstance();
            $application = Application::getInstance();
            $value = $application->hasCurrentRoute()
                ? $application->getCurrentRoute()->getParameterValue($attribute->name)
                : null;
            if (!is_string($value)
                || (null !== $attribute->pattern && 1 !== preg_match($attribute->pattern, $value))) {
                throw new HttpException($attribute->errorCode, $attribute->errorStatus);
            }
            $requestData[$parameter->getName()] = $value;
        }

        return $requestData;
    }
}
