<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Interface;

/**
 * DTO запроса с одним изображением в multipart-поле `file`: маппер заполняет `tmpName` и `bytes`,
 * параметры маршрута — по атрибутам, как у обычного request DTO.
 */
interface RequestImageDtoInterface extends DtoInterface {}
