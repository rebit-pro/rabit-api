<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Application\Max\Dto\MaxUpdateInputDto;
use Morefoto\Support\Application\Max\UseCase\HandleMaxUpdateUseCase;
use Morefoto\Support\Application\Question\Dto\SendGuestFeedbackInputDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Application\Question\UseCase\DeliverQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\SendGuestFeedbackUseCase;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Morefoto\Support\Tests\Unit\Double\FixedClock;
use Morefoto\Support\Tests\Unit\Double\ImmediateTransaction;
use Morefoto\Support\Tests\Unit\Double\InMemoryQuestions;
use Morefoto\Support\Tests\Unit\Double\RecordingPublisher;
use Morefoto\Support\Tests\Unit\Double\ScriptedMaxMessenger;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class GuestFeedbackTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';
    private const int CHAT = -72000000001;

    private InMemoryQuestions $questions;
    private RecordingPublisher $publisher;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->questions = new InMemoryQuestions();
        $this->publisher = new RecordingPublisher();
        $this->clock = new FixedClock();
    }

    public function testGuestFeedbackBecomesPendingReplyWithContact(): void
    {
        $number = $this->send()->execute(new SendGuestFeedbackInputDto(' Ольга ', ' +7 (900) 123-45-67 ', " Не приходит письмо\r\n "), new IdempotencyKey(self::KEY));

        self::assertSame(100, $number);
        self::assertSame(['guest', null, null, null, 'Ольга', 'Страница входа в кабинет · контакт: +7 (900) 123-45-67'], [
            $this->questions->questions[100]['author'], $this->questions->questions[100]['keyHash'], $this->questions->questions[100]['groupId'],
            $this->questions->questions[100]['staffUserId'], $this->questions->questions[100]['authorName'], $this->questions->questions[100]['context'],
        ]);
        self::assertSame(['guest', 'Не приходит письмо', 'pending'], [$this->questions->messages[1]['author'], $this->questions->messages[1]['body'], $this->questions->messages[1]['status']]);
        self::assertSame([1], $this->publisher->published);
    }

    public function testRepeatReturnsTheSameNumberAndAnotherBodyConflicts(): void
    {
        $input = new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Не могу войти');
        $first = $this->send()->execute($input, new IdempotencyKey(self::KEY));
        $second = $this->send()->execute($input, new IdempotencyKey(strtoupper(self::KEY)));

        self::assertSame($first, $second);
        self::assertCount(1, $this->questions->messages);
        self::assertSame([1], $this->publisher->published);

        $this->expectExceptionObject(new HttpException('IDEMPOTENCY_CONFLICT', 409));
        $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Другой текст'), new IdempotencyKey(self::KEY));
    }

    public function testGuestFeedbackIsLimitedPerHourForTheWholeSite(): void
    {
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_HOUR; ++$index) {
            $this->send()->execute(new SendGuestFeedbackInputDto('Гость', 'guest@example.com', 'Обращение ' . $index), new IdempotencyKey(sprintf('%032x', $index + 1)));
        }

        try {
            $this->send()->execute(new SendGuestFeedbackInputDto('Гость', 'guest@example.com', 'Лишнее'), new IdempotencyKey(sprintf('%032x', 999)));
            self::fail('The limit was not applied.');
        } catch (HttpException $error) {
            self::assertSame([429, 'RATE_LIMITED'], [$error->getCode(), $error->getMessage()]);
        }
        $this->clock->now = $this->clock->now->modify('+61 minutes');
        self::assertGreaterThan(0, $this->send()->execute(new SendGuestFeedbackInputDto('Гость', 'guest@example.com', 'Через час'), new IdempotencyKey(sprintf('%032x', 1000))));
    }

    public function testContactMustLeadBackToTheGuest(): void
    {
        $policy = new QuestionTextPolicy();

        self::assertSame('olga@example.com', $policy->contact('  olga@example.com '));
        self::assertSame('+7 900 123-45-67', $policy->contact("+7  900\t123-45-67"));
        foreach (['', '   ', 'Ольга', '123-45', 'olga@example', str_repeat('1', 121), "olga@example.com\u{0007}", "\xff"] as $contact) {
            try {
                $policy->contact($contact);
                self::fail('Accepted contact: ' . $contact);
            } catch (HttpException $error) {
                self::assertSame([422, 'INVALID_FEEDBACK_CONTACT'], [$error->getCode(), $error->getMessage()]);
            }
        }
    }

    public function testInvalidTextIsRejectedBeforeStorage(): void
    {
        try {
            $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', '   '), new IdempotencyKey(self::KEY));
            self::fail('Empty message accepted.');
        } catch (HttpException $error) {
            self::assertSame('INVALID_QUESTION_MESSAGE', $error->getMessage());
        }
        self::assertSame([], $this->questions->questions);
        self::assertSame([], $this->publisher->published);
    }

    public function testCuratorsSeeTheContactAndRepliesInMaxAreNotStored(): void
    {
        $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Не могу войти'), new IdempotencyKey(self::KEY));
        $max = new ScriptedMaxMessenger([new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, 'mid.guest')]);
        (new DeliverQuestionMessageUseCase(new ImmediateTransaction(), $this->questions, $max, new MaxQuestionTextBuilder(), $this->publisher, $this->clock, self::CHAT))->execute(1);

        self::assertSame(
            "Обращение №100 · гость «Ольга»\nСтраница входа в кабинет · контакт: olga@example.com\n———\nНе могу войти\n———\nСайт не покажет ответ: свяжитесь по контакту выше.",
            $max->sent[0]->text,
        );
        (new HandleMaxUpdateUseCase(new ImmediateTransaction(), $this->questions, $this->questions, new QuestionTextPolicy(), $this->clock, self::CHAT))
            ->execute(new MaxUpdateInputDto('message_created', self::CHAT, 'chat', 'mid.reply', 'mid.guest', 'Перезвоню', 'Рита', false))
        ;
        self::assertCount(1, $this->questions->messages);
    }

    private function send(): SendGuestFeedbackUseCase
    {
        return new SendGuestFeedbackUseCase(
            new ImmediateTransaction(),
            $this->questions,
            new QuestionMessageRecorder($this->questions),
            new QuestionTextPolicy(),
            new MaxQuestionTextBuilder(),
            $this->publisher,
            $this->clock,
        );
    }
}
