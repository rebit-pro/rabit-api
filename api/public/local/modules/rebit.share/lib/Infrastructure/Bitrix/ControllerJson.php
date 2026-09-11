<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Bitrix;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Rebit\Share\Infrastructure\Interface\SerializerInterface;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/**
 * Совместимый с Bitrix класс для возврата Json-ответа контроллера с использованием нашего сериалайзера.
 */
final class ControllerJson extends Json
{
    /**
     * @param int $options опции для json_encode
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        array|ResponseDtoInterface|null $data = null,
        int $options = 0,
    ) {
        parent::__construct($data, $options);
    }

    /**
     * @throws ArgumentTypeException
     */
    public function setData($data): self
    {
        $this->data = $this->serializer->serialize($data, $this->jsonEncodingOptions);

        return $this->setContent($this->data);
    }
}
