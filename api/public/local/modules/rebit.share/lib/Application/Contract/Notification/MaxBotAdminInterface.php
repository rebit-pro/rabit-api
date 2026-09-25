<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification;

use Rebit\Share\Application\Contract\Notification\Dto\MaxBotOutputDto;

/** Служебные операции бота MAX для консольной настройки: проверка токена и подписка webhook. */
interface MaxBotAdminInterface
{
    /** @throws \RuntimeException если токен не настроен или MAX недоступен */
    public function bot(): MaxBotOutputDto;

    /**
     * @param list<string> $updateTypes
     *
     * @throws \RuntimeException если MAX отклонил подписку
     */
    public function subscribe(string $url, string $secret, array $updateTypes): void;

    /**
     * @return list<string> URL действующих подписок
     *
     * @throws \RuntimeException если MAX недоступен
     */
    public function subscriptions(): array;
}
