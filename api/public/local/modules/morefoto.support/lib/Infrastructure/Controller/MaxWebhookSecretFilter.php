<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpRequest;
use Rebit\Share\Shared\Exception\HttpException;

/** Пропускает к webhook только запросы MAX с секретом подписки; без настроенного секрета отвергает все. */
final class MaxWebhookSecretFilter extends Base
{
    private const string HEADER_NAME = 'X-Max-Bot-Api-Secret';

    public function __construct(private readonly string $secret)
    {
        parent::__construct();
    }

    public function onBeforeAction(Event $event): ?EventResult
    {
        /** @var HttpRequest $request */
        $request = $event->getParameter('controller')->getRequest();
        $received = (string)$request->getHeader(self::HEADER_NAME);
        if ('' === $this->secret || '' === $received || !hash_equals($this->secret, $received)) {
            throw new HttpException('UNAUTHORIZED', 401);
        }

        return null;
    }
}
