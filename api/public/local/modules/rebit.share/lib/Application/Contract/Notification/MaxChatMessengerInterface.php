<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification;

use Rebit\Share\Application\Contract\Notification\Dto\MaxChatMessageInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;

/**
 * Отправка текста в групповой чат MAX от имени бота.
 *
 * Исход всегда возвращается значением: сетевые и HTTP-ошибки не бросаются, а классифицируются,
 * потому что у POST /messages нет ключа идемпотентности и повтор после неизвестного исхода может дать дубль.
 */
interface MaxChatMessengerInterface
{
    public function send(MaxChatMessageInputDto $message): MaxChatSendOutputDto;
}
