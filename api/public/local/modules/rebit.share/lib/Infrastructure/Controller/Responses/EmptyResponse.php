<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\HttpResponse;

/**
 * Ответ без тела (201, 202, 204): клиенту достаточно кода состояния.
 * Отдельный тип позволяет concrete controller объявлять результат action без классов Bitrix.
 */
final class EmptyResponse extends HttpResponse
{
    /**
     * @throws ArgumentTypeException
     */
    public function __construct(int $status)
    {
        parent::__construct();
        $this->setContent(null);
        $this->setStatus($status);
    }
}
