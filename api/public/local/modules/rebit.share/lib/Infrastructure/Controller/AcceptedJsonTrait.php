<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/** Единый ответ 202: запрос принят, результат не раскрывается (например, отправка письма по адресу). */
trait AcceptedJsonTrait
{
    final protected function acceptedJson(array|ResponseDtoInterface $data): ControllerJson
    {
        $response = $this->json($data);
        $response->setStatus(self::HTTP_ACCEPTED_CODE);

        return $response;
    }
}
