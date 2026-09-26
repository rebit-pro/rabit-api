<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\ClientAddress;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Дополняет данные DTO значениями из HTTP-заголовков, IP клиента и текущего маршрута по атрибутам конструктора.
 * Запрещает подменять эти поля через body/query и проверяет формат параметров маршрута до гидрации DTO.
 *
 * @internal
 */
final readonly class RequestTechnicalValues
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, mixed>     $requestData
     *
     * @return array<string, mixed>
     *
     * @throws HttpException
     */
    public function append(\ReflectionClass $reflection, array $requestData): array
    {
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            return $requestData;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $header = $parameter->getAttributes(RequestHeader::class)[0] ?? null;
            $route = $parameter->getAttributes(RouteParameter::class)[0] ?? null;
            $client = [] !== $parameter->getAttributes(ClientAddress::class);
            if ((null !== $header || null !== $route || $client) && array_key_exists($parameter->getName(), $requestData)) {
                throw new HttpException('UNKNOWN_FIELD', 422);
            }
            if ($client) {
                $requestData[$parameter->getName()] = (new ClientAddressResolver())
                    ->resolve($this->request->getRemoteAddress(), $this->request->getHeader('X-Forwarded-For'))
                ;
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
