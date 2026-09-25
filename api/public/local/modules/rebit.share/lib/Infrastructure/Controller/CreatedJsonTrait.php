<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/** Единые ответы 201 с адресом созданного ресурса и 202 с принятой заявкой для публичных и защищённых JSON API. */
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

    final protected function acceptedJson(
        array|ResponseDtoInterface $data,
    ): ControllerJson {
        $response = $this->json($data);
        $response->setStatus(self::HTTP_ACCEPTED_CODE);

        return $response;
    }
}
