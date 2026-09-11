<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\HttpResponse;

/**
 * @internal
 *
 * Абстрактный класс для классов формирования ответов контролера
 *
 * @template TResponse of HttpResponse
 */
abstract class AbstractResponse
{
    protected HttpResponse $response;

    abstract protected function buildResponse(): HttpResponse;

    /**
     * @return TResponse
     */
    final public function getResponse(): HttpResponse
    {
        if (!isset($this->response)) {
            $this->response = $this->buildResponse();
        }

        return $this->response;
    }
}
