<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/** Единый ответ 201 с адресом созданного ресурса для публичных и защищённых JSON API. */
trait CreatedJsonTrait
{
    final protected function createdJson(
        array|ResponseDtoInterface $data,
        string $location,
    ): ControllerJson {
        $response = $this->json($data);
        $response->setStatus(self::HTTP_CREATED_CODE);
        $response->addHeader('Location', $location);

        return $response;
    }
}
