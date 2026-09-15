<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Port;

/**
 * Изолированный канал доставки заявок mos-dizel.ru.
 *
 * Отдельный тип не позволяет HTTP-контроллеру получить общий Telegram notifier.
 */
interface MosDizelLeadNotifierInterface extends LeadNotifierInterface {}
