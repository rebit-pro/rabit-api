<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Repository;

/** Журнал входящих уведомлений провайдера без тела запроса: дедупликация и итог обработки. */
interface PaymentNotificationRepositoryInterface
{
    /** @return bool true — уведомление с этим ключом уже обработано */
    public function register(string $provider, string $eventKey, string $event, string $objectId, string $receivedAt): bool;

    public function complete(string $provider, string $eventKey, string $result, string $processedAt): void;
}
