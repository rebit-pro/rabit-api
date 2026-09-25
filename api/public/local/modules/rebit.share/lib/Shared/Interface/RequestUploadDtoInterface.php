<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Interface;

/**
 * DTO запроса с одним файлом в multipart-поле `file` и текстовыми полями формы.
 * Маппер заполняет `tmpName`, `filename` и `bytes`, поля формы — по атрибуту `FormField`,
 * параметры маршрута и заголовки — по атрибутам, как у обычного request DTO.
 */
interface RequestUploadDtoInterface extends DtoInterface {}
