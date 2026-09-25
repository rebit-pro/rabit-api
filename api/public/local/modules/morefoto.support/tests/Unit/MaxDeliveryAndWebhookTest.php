<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Application\Max\Dto\MaxUpdateInputDto;
use Morefoto\Support\Application\Max\UseCase\HandleMaxUpdateUseCase;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\UseCase\DeliverQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\DispatchPendingQuestionMessagesUseCase;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Tests\Unit\Double\FixedClock;
use Morefoto\Support\Tests\Unit\Double\ImmediateTransaction;
use Morefoto\Support\Tests\Unit\Double\InMemoryQuestions;
use Morefoto\Support\Tests\Unit\Double\RecordingPublisher;
use Morefoto\Support\Tests\Unit\Double\ScriptedMaxMessenger;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class MaxDeliveryAndWebhookTest extends TestCase
{
    private const int CHAT = -72000000001;

    private InMemoryQuestions $questions;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->questions = new InMemoryQuestions();
        $this->clock = new FixedClock();
        $question = $this->questions->createParent(str_repeat('a', 64), 42, 'Мария', 'Сад «Солнышко», группа «Пчёлки»', $this->clock->now);
        $this->questions->addMessage($question, AuthorEnum::PARENT, 'Мария', 'Когда будут фото?', $this->clock->now);
    }

    public function testDeliveredReplyKeepsMidAndSendsReadableText(): void
    {
        $max = new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, 'mid.1')]);
        $this->deliver($max)->execute(1);

        self::assertSame(['delivered', 'mid.1', 1], [$this->questions->messages[1]['status'], $this->questions->messages[1]['mid'], $this->questions->messages[1]['attempts']]);
        self::assertSame(self::CHAT, $max->sent[0]->chatId);
        self::assertSame(
            "Вопрос №100 · родитель «Мария»\nСад «Солнышко», группа «Пчёлки»\n———\nКогда будут фото?\n———\nЧтобы ответить, нажмите «Ответить» на этом сообщении.",
            $max->sent[0]->text,
        );
        $this->deliver(new ScriptedMaxMessenger([]))->execute(1);
    }

    public function testSafeFailureIsRetriedLaterAndUnknownIsNeverRepeated(): void
    {
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_http_429')]))->execute(1);
        self::assertSame(['pending', 'max_http_429'], [$this->questions->messages[1]['status'], $this->questions->messages[1]['error']]);
        self::assertEquals($this->clock->now->modify('+30 seconds'), $this->questions->messages[1]['nextAt']);

        $this->deliver(new ScriptedMaxMessenger([]))->execute(1);
        $this->clock->now = $this->clock->now->modify('+31 seconds');
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::UNKNOWN, errorCode: 'max_transport_error')]))->execute(1);
        self::assertSame(['unknown', 2], [$this->questions->messages[1]['status'], $this->questions->messages[1]['attempts']]);

        $publisher = new RecordingPublisher();
        self::assertSame(0, (new DispatchPendingQuestionMessagesUseCase(new ImmediateTransaction(), $this->questions, $publisher, $this->clock))->execute(100));
    }

    public function testRejectedAndExhaustedRepliesFail(): void
    {
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::REJECTED, errorCode: 'max_http_403')]))->execute(1);
        self::assertSame('failed', $this->questions->messages[1]['status']);

        $this->questions->messages[1]['status'] = 'pending';
        $this->questions->messages[1]['attempts'] = DeliverQuestionMessageUseCase::MAX_ATTEMPTS - 1;
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_http_503')]))->execute(1);
        self::assertSame('failed', $this->questions->messages[1]['status']);
    }

    public function testWithoutConfiguredGroupReplyStaysPending(): void
    {
        $this->deliver(new ScriptedMaxMessenger([]), 0)->execute(1);
        self::assertSame(['pending', 0], [$this->questions->messages[1]['status'], $this->questions->messages[1]['attempts']]);

        $publisher = new RecordingPublisher();
        self::assertSame(1, (new DispatchPendingQuestionMessagesUseCase(new ImmediateTransaction(), $this->questions, $publisher, $this->clock))->execute(100));
        self::assertSame([1], $publisher->published);
    }

    public function testStaleAttemptBecomesUnknownInsteadOfBeingSentAgain(): void
    {
        $this->questions->messages[1]['status'] = 'processing';
        $this->questions->messages[1]['startedAt'] = $this->clock->now->modify('-10 minutes');

        (new DispatchPendingQuestionMessagesUseCase(new ImmediateTransaction(), $this->questions, new RecordingPublisher(), $this->clock))->execute(100);

        self::assertSame('unknown', $this->questions->messages[1]['status']);
    }

    public function testCuratorReplyToBotMessageIsStoredOnce(): void
    {
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, 'mid.1')]))->execute(1);
        $reply = $this->update(mid: 'mid.2', replyTo: 'mid.1', text: "  Готовы в пятницу\u{0007}  ");

        $this->webhook()->execute($reply);
        $this->webhook()->execute($reply);

        $curator = array_values(array_filter($this->questions->messages, static fn(array $message): bool => 'curator' === $message['author']));
        self::assertCount(1, $curator);
        self::assertSame(['Рита Смирнова', 'Готовы в пятницу', 100], [$curator[0]['authorName'], $curator[0]['body'], $curator[0]['questionId']]);
        self::assertSame(self::CHAT, $this->questions->chats[self::CHAT]['chatId']);
    }

    public function testOtherGroupMessagesDoNotReachTheSite(): void
    {
        $this->deliver(new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, 'mid.1')]))->execute(1);
        $cases = [
            'no reply' => $this->update(mid: 'mid.3', replyTo: null),
            'reply to a human' => $this->update(mid: 'mid.4', replyTo: 'mid.unknown'),
            'another chat' => $this->update(mid: 'mid.5', replyTo: 'mid.1', chatId: -1),
            'bot itself' => $this->update(mid: 'mid.6', replyTo: 'mid.1', bot: true),
            'attachment only' => $this->update(mid: 'mid.7', replyTo: 'mid.1', text: null),
            'edited message' => new MaxUpdateInputDto('message_edited', self::CHAT, 'chat', 'mid.8', 'mid.1', 'Правка', 'Рита', false),
        ];
        foreach ($cases as $update) {
            $this->webhook()->execute($update);
        }

        self::assertCount(1, $this->questions->messages, implode(', ', array_keys($cases)));
    }

    public function testBotMembershipIsRememberedForChoosingTheGroup(): void
    {
        $this->webhook(0)->execute(new MaxUpdateInputDto('bot_added', -555, null, null, null, null, 'Александр', false));
        $this->webhook(0)->execute(new MaxUpdateInputDto('message_created', 12345, 'dialog', 'mid.9', null, 'Привет', 'Посторонний', false));

        self::assertSame([-555], array_keys($this->questions->chats));
        self::assertTrue($this->questions->chats[-555]['botPresent']);
    }

    private function deliver(ScriptedMaxMessenger $max, int $chatId = self::CHAT): DeliverQuestionMessageUseCase
    {
        return new DeliverQuestionMessageUseCase(new ImmediateTransaction(), $this->questions, $max, new MaxQuestionTextBuilder(), $this->clock, $chatId);
    }

    private function webhook(int $chatId = self::CHAT): HandleMaxUpdateUseCase
    {
        return new HandleMaxUpdateUseCase(new ImmediateTransaction(), $this->questions, $this->questions, new QuestionTextPolicy(), $this->clock, $chatId);
    }

    private function update(string $mid, ?string $replyTo, ?string $text = 'Ответ', int $chatId = self::CHAT, bool $bot = false): MaxUpdateInputDto
    {
        return new MaxUpdateInputDto('message_created', $chatId, 'chat', $mid, $replyTo, $text, 'Рита Смирнова', $bot);
    }
}
