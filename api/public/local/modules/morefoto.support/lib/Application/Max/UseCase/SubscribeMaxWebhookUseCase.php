<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Max\UseCase;

use Rebit\Share\Application\Contract\Notification\MaxBotAdminInterface;

/** Подписывает бота MAX на webhook стенда с секретом из защищённой конфигурации: сообщения, добавление и удаление бота. */
final readonly class SubscribeMaxWebhookUseCase
{
    public const array UPDATE_TYPES = ['message_created', 'bot_added', 'bot_removed'];

    public function __construct(
        private MaxBotAdminInterface $admin,
        private string $secret,
    ) {}

    public function execute(string $url): void
    {
        if (1 !== preg_match('~^https://[a-z0-9.-]+/api/v1/webhooks/max/updates$~D', $url)) {
            throw new \InvalidArgumentException('Webhook URL must be https://<host>/api/v1/webhooks/max/updates.');
        }
        if (1 !== preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $this->secret)) {
            throw new \InvalidArgumentException('MOREFOTO_SUPPORT_MAX_WEBHOOK_SECRET must be 32–256 characters [A-Za-z0-9_-].');
        }
        $this->admin->subscribe($url, $this->secret, self::UPDATE_TYPES);
    }
}
