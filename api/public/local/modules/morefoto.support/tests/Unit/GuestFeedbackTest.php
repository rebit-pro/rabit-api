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
use Morefoto\Support\Infrastructure\Crypto\GuestAddressHasher;
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
    private const string ADDRESS = '203.0.113.7';
    private const string SECRET = 'unit-test-secret-0123456789abcdef0123';

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
        $number = $this->send()->execute(new SendGuestFeedbackInputDto(' Ольга ', ' +7 (900) 123-45-67 ', " Не приходит письмо\r\n ", self::ADDRESS), new IdempotencyKey(self::KEY));

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
        $input = new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Не могу войти', self::ADDRESS);
        $first = $this->send()->execute($input, new IdempotencyKey(self::KEY));
        $second = $this->send()->execute($input, new IdempotencyKey(strtoupper(self::KEY)));

        self::assertSame($first, $second);
        self::assertCount(1, $this->questions->messages);
        self::assertSame([1], $this->publisher->published);

        $this->expectExceptionObject(new HttpException('IDEMPOTENCY_CONFLICT', 409));
        $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Другой текст', self::ADDRESS), new IdempotencyKey(self::KEY));
    }

    public function testGuestFeedbackIsLimitedPerHourForTheWholeSite(): void
    {
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_HOUR; ++$index) {
            $this->feedback('Обращение ' . $index, '198.51.100.' . ($index + 1), $index + 1);
        }

        $this->assertRateLimited('Лишнее', '192.0.2.1', 999);
        $this->clock->now = $this->clock->now->modify('+61 minutes');
        self::assertGreaterThan(0, $this->feedback('Через час', '192.0.2.1', 1000));
    }

    public function testOneAddressCannotTakeTheWholeSiteLimit(): void
    {
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('Обращение ' . $index, self::ADDRESS, $index + 1);
        }

        $this->assertRateLimited('Лишнее', self::ADDRESS, 999);
        self::assertCount(SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR, $this->questions->questions);
        self::assertGreaterThan(0, $this->feedback('Другой гость', '198.51.100.20', 1000));
        // The same /64 of an IPv6 subscriber is one address.
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('IPv6 ' . $index, '2001:db8:1:2::' . dechex($index + 1), 2000 + $index);
        }
        $this->assertRateLimited('IPv6 лишнее', '2001:db8:1:2:ffff::1', 2999);
    }

    public function testAddressWindowRestartsAfterAnHourAndStoresOnlyTheHash(): void
    {
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('Обращение ' . $index, self::ADDRESS, $index + 1);
        }
        self::assertSame([(new GuestAddressHasher(self::SECRET))->hash(self::ADDRESS)], array_keys($this->questions->guestAddresses));
        self::assertStringNotContainsString(self::ADDRESS, serialize($this->questions->guestAddresses));

        $this->clock->now = $this->clock->now->modify('+61 minutes');
        $this->feedback('Другой гость', '198.51.100.20', 100);
        self::assertCount(1, $this->questions->guestAddresses, 'the stale window is deleted');
        self::assertGreaterThan(0, $this->feedback('Через час', self::ADDRESS, 101));
        self::assertSame(1, $this->questions->guestAddresses[(new GuestAddressHasher(self::SECRET))->hash(self::ADDRESS)]['questions']);
    }

    public function testRepeatIsNotLimitedByTheAddress(): void
    {
        $first = $this->feedback('Обращение 0', self::ADDRESS, 1);
        for ($index = 1; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('Обращение ' . $index, self::ADDRESS, $index + 1);
        }

        self::assertSame($first, $this->feedback('Обращение 0', self::ADDRESS, 1));
    }

    public function testParallelRepeatAtTheAddressLimitGetsTheStoredNumber(): void
    {
        $this->useAddressUpToTheLastPlace();
        // Both requests missed the key; the parallel one takes the last place while this one waits for the address lock.
        $this->questions->whileWaitingForAddress = fn(): int => $this->feedback('Пятое', self::ADDRESS, 5);

        self::assertSame(104, $this->feedback('Пятое', self::ADDRESS, 5));
        self::assertCount(5, $this->questions->questions);
        self::assertSame([1, 2, 3, 4, 5], $this->publisher->published);
        self::assertSame(5, $this->addressQuestions(self::ADDRESS));
    }

    public function testParallelRequestWithAnotherBodyConflicts(): void
    {
        $this->useAddressUpToTheLastPlace();
        $this->questions->whileWaitingForAddress = fn(): int => $this->feedback('Пятое', self::ADDRESS, 5);

        $this->assertRefused(409, 'IDEMPOTENCY_CONFLICT', 'Другой текст', 5);
    }

    public function testParallelRequestWithAnotherKeyIsLimited(): void
    {
        $this->useAddressUpToTheLastPlace();
        $this->questions->whileWaitingForAddress = fn(): int => $this->feedback('Пятое', self::ADDRESS, 5);

        $this->assertRefused(429, 'RATE_LIMITED', 'Шестое', 6);
    }

    public function testRepeatFromAnotherAddressIsNotLimitedThere(): void
    {
        $first = $this->feedback('Обращение', self::ADDRESS, 1);
        for ($index = 0; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('Другой гость ' . $index, '198.51.100.20', 10 + $index);
        }

        self::assertSame($first, $this->feedback('Обращение', '198.51.100.20', 1));
        self::assertCount(1 + SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR, $this->questions->questions);
        self::assertSame([1, 5], [$this->addressQuestions(self::ADDRESS), $this->addressQuestions('198.51.100.20')]);
    }

    public function testWithoutTheServerSecretNothingIsStored(): void
    {
        $refusal = '';
        try {
            $this->send('')->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Не могу войти', self::ADDRESS), new IdempotencyKey(self::KEY));
        } catch (\RuntimeException $error) {
            $refusal = $error->getMessage();
        }
        self::assertStringContainsString('REBIT_ENCRYPTION_KEY', $refusal);
        self::assertSame([[], [], []], [$this->questions->questions, $this->questions->guestAddresses, $this->publisher->published]);
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
            $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', '   ', self::ADDRESS), new IdempotencyKey(self::KEY));
            self::fail('Empty message accepted.');
        } catch (HttpException $error) {
            self::assertSame('INVALID_QUESTION_MESSAGE', $error->getMessage());
        }
        self::assertSame([], $this->questions->questions);
        self::assertSame([], $this->publisher->published);
    }

    public function testCuratorsSeeTheContactAndRepliesInMaxAreNotStored(): void
    {
        $this->send()->execute(new SendGuestFeedbackInputDto('Ольга', 'olga@example.com', 'Не могу войти', self::ADDRESS), new IdempotencyKey(self::KEY));
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

    private function useAddressUpToTheLastPlace(): void
    {
        for ($index = 1; $index < SendGuestFeedbackUseCase::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR; ++$index) {
            $this->feedback('Обращение ' . $index, self::ADDRESS, $index);
        }
    }

    /** The parallel request took the last place of the address: nothing else is stored. */
    private function assertRefused(int $status, string $code, string $message, int $key): void
    {
        try {
            $this->feedback($message, self::ADDRESS, $key);
            self::fail('The request was accepted.');
        } catch (HttpException $error) {
            self::assertSame([$status, $code], [$error->getCode(), $error->getMessage()]);
        }
        self::assertCount(5, $this->questions->questions);
        self::assertSame([1, 2, 3, 4, 5], $this->publisher->published);
        self::assertSame(5, $this->addressQuestions(self::ADDRESS));
    }

    private function addressQuestions(string $address): int
    {
        return $this->questions->guestAddresses[(new GuestAddressHasher(self::SECRET))->hash($address)]['questions'];
    }

    private function feedback(string $message, string $address, int $key): int
    {
        return $this->send()->execute(new SendGuestFeedbackInputDto('Гость', 'guest@example.com', $message, $address), new IdempotencyKey(sprintf('%032x', $key)));
    }

    private function assertRateLimited(string $message, string $address, int $key): void
    {
        $before = count($this->questions->questions);
        try {
            $this->feedback($message, $address, $key);
            self::fail('The limit was not applied.');
        } catch (HttpException $error) {
            self::assertSame([429, 'RATE_LIMITED'], [$error->getCode(), $error->getMessage()]);
        }
        self::assertCount($before, $this->questions->questions);
    }

    private function send(string $secret = self::SECRET): SendGuestFeedbackUseCase
    {
        return new SendGuestFeedbackUseCase(
            new ImmediateTransaction(),
            $this->questions,
            new QuestionMessageRecorder($this->questions),
            new QuestionTextPolicy(),
            new MaxQuestionTextBuilder(),
            $this->publisher,
            new GuestAddressHasher($secret),
            $this->clock,
        );
    }
}
