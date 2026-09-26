<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Enum;

enum MessengerQueueEnum: string
{
    case AUDIT = 'audit';
    case NOTIFICATION_EMAIL = 'notificationEmail';
    case MEDIA_PROCESSING = 'mediaProcessing';
    case SUPPORT_MAX = 'supportMax';
    case FILES_ARCHIVE = 'filesArchive';
    case FAILED = 'messengerFailed';

    /**
     * Возвращает ключ сервиса транспорта в DI-контейнере.
     *
     * Сам `value` enum используется как имя очереди/роутинга в Messenger,
     * а транспорт для этой очереди регистрируется отдельным сервисом
     * с суффиксом `_transport`, например `audit_transport`.
     */
    public function transportKey(): string
    {
        return $this->value . '_transport';
    }
}
