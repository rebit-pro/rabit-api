<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Infrastructure\Helpers\RequestHelper;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
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
     */
    public function map(string $className): object
    {
        $requestData = RequestHelper::collectRequestValues($this->request);

        return ArrayToDtoMapper::map($requestData, $className);
    }
}
