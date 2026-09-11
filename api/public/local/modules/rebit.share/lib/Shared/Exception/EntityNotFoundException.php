<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Exception;

class EntityNotFoundException extends HttpException
{
    public const int HTTP_DEFAULT_EXCEPTION_CODE = 404;
    public const string DEFAULT_ERROR_MESSAGE = 'Сущность не найдена';
}
