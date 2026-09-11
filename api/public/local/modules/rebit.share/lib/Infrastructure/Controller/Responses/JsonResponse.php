<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Interface\SerializerInterface;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/**
 * Класс для формирования Json-ответа API в нашем формате.
 *
 * Добавляет к Json-ответу Битрикса метаданные и используем свой сериализатор.
 *
 * @extends AbstractResponse<\Rebit\Share\Infrastructure\Bitrix\ControllerJson>
 */
final class JsonResponse extends AbstractResponse
{
    /**
     * @param int $options опции для json_encode
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly array|ResponseDtoInterface|null $data = null,
        private readonly array $meta = [],
        private readonly int $options = 0,
    ) {}

    protected function buildResponse(): ControllerJson
    {
        $payload = [
            'data' => $this->data,
        ];

        if (!empty($this->meta)) {
            $payload['meta'] = $this->meta;
        }

        return new ControllerJson($this->serializer, $payload, $this->options);
    }
}
