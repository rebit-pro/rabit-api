<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Audit\Port;

use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

/**
 * Контракт publisher'а аудит-событий (audit).
 */
interface AuditPublisherInterface extends MessagePublisherInterface {}
