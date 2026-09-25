<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Presentation\Max\MaxUpdateMapper;
use Morefoto\Support\Presentation\Max\Request\Dto\MaxUpdateRequestDto;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Helpers\RequestHelper;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Morefoto\Support\Application\Max\Dto\MaxUpdateInputDto;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Real MAX Update payloads (schema 1a4a502f) through the same decoding as the HTTP request.
 *
 * @internal
 */
final class MaxUpdateContractTest extends TestCase
{
    public function testCuratorReplyInGroupIsFlattened(): void
    {
        $update = $this->map(<<<'JSON'
{"update_type":"message_created","timestamp":1790000000000,"user_locale":"ru",
 "message":{"sender":{"user_id":101,"first_name":"Рита","last_name":"Смирнова","username":null,"is_bot":false,"last_activity_time":1790000000000},
  "recipient":{"chat_id":-72000000001,"chat_type":"chat","user_id":null},"timestamp":1790000000000,
  "link":{"type":"reply","sender":{"user_id":488939651,"first_name":"Море Фото","is_bot":true,"last_activity_time":1},"chat_id":-72000000001,
   "message":{"mid":"mid.bot","seq":11,"text":"Вопрос №1","attachments":null,"markup":[]}},
  "body":{"mid":"mid.reply","seq":12,"text":"Готовы в пятницу.","attachments":[{"type":"image","payload":{"url":"https://example.invalid/x"}}],"markup":[]},
  "stat":null,"url":null}}
JSON);

        self::assertSame(['message_created', -72000000001, 'chat', 'mid.reply', 'mid.bot', 'Готовы в пятницу.', 'Рита Смирнова', false], [
            $update->updateType, $update->chatId, $update->chatType, $update->mid, $update->replyToMid, $update->text, $update->senderName, $update->senderIsBot,
        ]);
    }

    public function testForwardAndPlainMessagesHaveNoReplyTarget(): void
    {
        $forward = $this->map('{"update_type":"message_created","timestamp":1,"message":{"recipient":{"chat_id":-1,"chat_type":"chat"},"timestamp":1,'
            . '"link":{"type":"forward","message":{"mid":"mid.bot","seq":1}},"body":{"mid":"mid.f","seq":2,"text":"x"}}}');
        $plain = $this->map('{"update_type":"message_created","timestamp":1,"message":{"sender":{"user_id":1,"first_name":"","is_bot":false},'
            . '"recipient":{"chat_id":5,"chat_type":"dialog","user_id":7},"timestamp":1,"body":{"mid":"mid.p","seq":1,"text":null}}}');

        self::assertNull($forward->replyToMid);
        self::assertSame('Куратор', $forward->senderName);
        self::assertSame([null, null, 'dialog', 5], [$plain->replyToMid, $plain->text, $plain->chatType, $plain->chatId]);
    }

    public function testBotMembershipCarriesTheChatAtTopLevel(): void
    {
        $update = $this->map('{"update_type":"bot_added","timestamp":1,"chat_id":-72000000002,"user":{"user_id":1,"first_name":"A","is_bot":false},"is_channel":false}');

        self::assertSame(['bot_added', -72000000002, null, null], [$update->updateType, $update->chatId, $update->mid, $update->replyToMid]);
    }

    private function map(string $json): MaxUpdateInputDto
    {
        $object = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(\stdClass::class, $object);
        $request = ArrayToDtoMapper::map(RequestHelper::jsonObjectsToArrays(get_object_vars($object)), MaxUpdateRequestDto::class);

        return (new MaxUpdateMapper())->update($request);
    }
}
