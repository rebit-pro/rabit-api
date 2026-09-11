<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Filters;

use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpResponse;
use Rebit\Share\Infrastructure\Controller\AbstractController;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;
use Rebit\Share\Infrastructure\Helpers\RequestHelper;
use Bitrix\Main\Engine\Response\Json;

/**
 * Логирует входящий запрос и его результаты.
 *
 * Канал логирования определяется автоматически по namespace контроллера,
 * если не передан явно. Например: Rebit\Auth\... → LogChannelEnum::auth
 */
final class LoggerFilter extends Base
{
    public function __construct(
        private readonly ?LogChannelEnum $channel = null,
        private readonly array $extraData = [],
    ) {
        parent::__construct();
    }

    /**
     * Логируем входящий запрос
     *
     * @param Event{
     *     moduleId: string,
     *     type: string,
     *     parameters: array{
     *         action: Action,
     *         controller: AbstractController,
     *     }
     * } $event
     */
    public function onBeforeAction(Event $event): ?EventResult
    {
        /** @var Controller $controller */
        $controller = $event->getParameter('controller');
        $channel = $this->resolveChannel($controller);

        $data = $this->extractRequestData($controller);
        Log::channel($channel)->info('REQUEST', array_merge($data, $this->extraData));

        return null;
    }

    /**
     * Логируем результат запроса
     *
     * @param Event{
     *     moduleId: string,
     *     type: string,
     *     parameters: array{
     *         action: Action,
     *         controller: AbstractController,
     *         result: HttpResponse,
     *     }
     * } $event
     *
     * @throws \JsonException
     */
    public function onAfterAction(Event $event): ?EventResult
    {
        /** @var Controller $controller */
        $controller = $event->getParameter('controller');
        /** @var HttpResponse $response */
        $response = $event->getParameter('result');
        $channel = $this->resolveChannel($controller);

        $requestData = $this->extractRequestData($controller);
        $responseData = $this->extractResponseData($response);
        $payload = array_merge($requestData, [
            'response' => $responseData,
        ]);

        Log::channel($channel)->info('RESPONSE', array_merge($payload, $this->extraData));

        return null;
    }

    /**
     * Определяет канал: явно переданный или автоматически по namespace контроллера.
     */
    private function resolveChannel(Controller $controller): LogChannelEnum
    {
        return $this->channel ?? LogChannelEnum::resolveFromClassName($controller::class);
    }

    /**
     * @return array{
     *     request: array<string, mixed>,
     * }
     */
    private function extractRequestData(Controller $controller): array
    {
        $request = $controller->getRequest();

        return [
            'request' => RequestHelper::collectRequestValues($request),
        ];
    }

    /**
     * Если ответ json, то возвращаем декодированный массив, иначе массив с текстом ответа.
     *
     * @throws \JsonException
     */
    private function extractResponseData(HttpResponse $response): array
    {
        $content = $response->getContent();
        if (!$response instanceof Json) {
            return [$content];
        }

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR) ?? [];
    }
}
