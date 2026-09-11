<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Filters;

use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpResponse;
use Rebit\Share\Infrastructure\Logger\LogSanitizer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

/** HTTP diagnostics never read request values, headers or response content. */
final class LoggerFilter extends Base
{
    /** @param array<array-key, mixed> $extraData */
    public function __construct(
        private readonly ?LogChannelEnum $channel = null,
        private readonly array $extraData = [],
    ) {
        parent::__construct();
    }

    public function onBeforeAction(Event $event): ?EventResult
    {
        $this->write('REQUEST', $event);

        return null;
    }

    public function onAfterAction(Event $event): ?EventResult
    {
        $this->write('RESPONSE', $event);

        return null;
    }

    private function write(string $message, Event $event): void
    {
        $controller = $event->getParameter('controller');
        if (!$controller instanceof Controller) {
            return;
        }

        $sanitizer = new LogSanitizer();
        $context = $sanitizer->context($this->extraData);
        $context['controller'] = $controller::class;
        $action = $event->getParameter('action');
        $context['operation'] = $controller::class . ($action instanceof Action ? '::' . $action->getName() : '');
        $context['method'] = $controller->getRequest()->getRequestMethod();
        $context['requestId'] = RequestIdGenerator::getRequestId();
        $context['durationMs'] = RequestIdGenerator::getDurationMs();
        $response = $event->getParameter('result');
        if ($response instanceof HttpResponse) {
            $context['httpStatus'] = $response->getStatus();
        }

        $channel = $this->channel ?? LogChannelEnum::resolveFromClassName($controller::class);
        Log::channel($channel)->info($message, $sanitizer->context($context));
    }
}
