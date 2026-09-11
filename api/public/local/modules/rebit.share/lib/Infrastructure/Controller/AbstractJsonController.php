<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Responses\JsonExceptionResponse;
use Rebit\Share\Infrastructure\Interface\SerializerInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/**
 * @internal
 *
 * Внутренний абстрактный класс для Json-контроллеров
 *
 * @extends AbstractController<ControllerJson>
 */
abstract class AbstractJsonController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    abstract protected function getResponse(
        array|ResponseDtoInterface $data,
        array $meta = [],
        ?SerializerInterface $serializer = null,
    ): ControllerJson;

    /**
     * Создает json из DTO или массива для ответа контроллера.
     * Алиас для getResponse.
     */
    final public function json(
        array|ResponseDtoInterface $data,
        array $meta = [],
        ?SerializerInterface $serializer = null,
    ): ControllerJson {
        return $this->getResponse($data, $meta, $serializer);
    }

    /**
     * Действия перед отдачей готового ответа.
     * Используем для формирования нашего формата ответа на исключение
     *
     * @throws ArgumentTypeException|\JsonException
     */
    public function finalizeResponse(Response|string $response): void
    {
        if (!$response instanceof HttpResponse) {
            $this->thrownException = new HttpException('Ответ контроллера должен быть HttpResponse или его наследник!');
        }

        parent::finalizeResponse($response);
    }

    protected function getExceptionResponse(): ControllerJson
    {
        $isDebug = (bool)Configuration::getValue('exception_handling')['debug'];

        return (new JsonExceptionResponse($this->thrownException, $isDebug))->getResponse();
    }
}
