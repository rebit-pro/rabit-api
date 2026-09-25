<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Application\Question\Contract\GalleryQuestionContextInterface;
use Morefoto\Support\Application\Question\Contract\StaffQuestionContextInterface;
use Morefoto\Support\Application\Question\Dto\AskGalleryQuestionInputDto;
use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;
use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\ParentQuestionAccess;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Application\Question\UseCase\AddGalleryQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\AddStaffQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\AskGalleryQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\GetGalleryQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\GetStaffQuestionUseCase;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Morefoto\Support\Infrastructure\Crypto\QuestionKeySeal;
use Morefoto\Support\Tests\Unit\Double\FixedClock;
use Morefoto\Support\Tests\Unit\Double\ImmediateTransaction;
use Morefoto\Support\Tests\Unit\Double\InMemoryQuestions;
use Morefoto\Support\Tests\Unit\Double\RecordingPublisher;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class QuestionUseCasesTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string KEY = '0123456789abcdef0123456789abcdef';

    private InMemoryQuestions $questions;
    private RecordingPublisher $publisher;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->questions = new InMemoryQuestions();
        $this->publisher = new RecordingPublisher();
        $this->clock = new FixedClock();
    }

    public function testParentQuestionCreatesConversationPendingReplyAndPrivateKey(): void
    {
        $output = $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, '  Мария ', " Когда будут фото?\r\n "), new IdempotencyKey(self::KEY));

        self::assertSame(100, $output->id);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string)$output->questionKey);
        self::assertCount(1, $output->messages);
        self::assertSame(['parent', 'Мария', 'Когда будут фото?', 'pending'], [$output->messages[0]->author, $output->messages[0]->authorName, $output->messages[0]->text, $output->messages[0]->deliveryStatus]);
        self::assertSame(hash('sha256', (string)$output->questionKey), $this->questions->questions[100]['keyHash']);
        self::assertSame('Сад «Солнышко», группа «Пчёлки», куратор группы: Рита', $this->questions->questions[100]['context']);
        self::assertSame([1], $this->publisher->published);
        foreach ($this->questions->keys as $stored) {
            self::assertStringNotContainsString((string)$output->questionKey, (string)$stored['sealedKey']);
        }
    }

    public function testRepeatedRequestReturnsTheSameKeyWithoutSecondReply(): void
    {
        $first = $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Когда будут фото?'), new IdempotencyKey(self::KEY));
        $second = $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Когда будут фото?'), new IdempotencyKey(strtoupper(self::KEY)));

        self::assertSame($first->questionKey, $second->questionKey);
        self::assertCount(1, $this->questions->messages);
        self::assertSame([1], $this->publisher->published);
    }

    public function testSameKeyWithAnotherBodyConflicts(): void
    {
        $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Когда будут фото?'), new IdempotencyKey(self::KEY));

        $this->expectExceptionObject(new HttpException('IDEMPOTENCY_CONFLICT', 409));
        $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Другой вопрос'), new IdempotencyKey(self::KEY));
    }

    public function testNewConversationsOfOneGroupAreLimitedPerHour(): void
    {
        for ($index = 0; $index < AskGalleryQuestionUseCase::QUESTIONS_PER_GROUP_HOUR; ++$index) {
            $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Родитель', 'Вопрос ' . $index), new IdempotencyKey(sprintf('%032x', $index + 1)));
        }

        try {
            $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Родитель', 'Лишний'), new IdempotencyKey(sprintf('%032x', 999)));
            self::fail('The limit was not applied.');
        } catch (HttpException $error) {
            self::assertSame([429, 'RATE_LIMITED'], [$error->getCode(), $error->getMessage()]);
        }
        $this->clock->now = $this->clock->now->modify('+61 minutes');
        self::assertNotNull($this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Родитель', 'Через час'), new IdempotencyKey(sprintf('%032x', 1000)))->id);
    }

    public function testInvalidTextIsRejectedBeforeTheGalleryIsRead(): void
    {
        $this->expectExceptionObject(new HttpException('INVALID_QUESTION_NAME', 422));
        $this->ask(static fn(): never => throw new \LogicException('Gallery must not be read.'))
            ->execute(new AskGalleryQuestionInputDto(self::TOKEN, str_repeat('Я', 61), 'Вопрос'), new IdempotencyKey(self::KEY))
        ;
    }

    public function testParentContinuesOnlyWithItsOwnKey(): void
    {
        $created = $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Первый'), new IdempotencyKey(self::KEY));
        $add = $this->add();

        $output = $add->execute($created->questionKey, 'Второй', new IdempotencyKey(str_repeat('b', 32)));
        $replay = $add->execute($created->questionKey, 'Второй', new IdempotencyKey(str_repeat('b', 32)));

        self::assertSame(['Первый', 'Второй'], array_map(static fn($message): string => $message->text, $replay->messages));
        self::assertCount(2, $output->messages);
        self::assertSame([1, 2], $this->publisher->published);
        foreach ([null, '', 'zz', str_repeat('c', 64)] as $wrong) {
            try {
                (new GetGalleryQuestionUseCase(new ParentQuestionAccess($this->questions), new QuestionHistory($this->questions)))->execute($wrong);
                self::fail('A wrong key opened a conversation.');
            } catch (HttpException $error) {
                self::assertSame([404, 'QUESTION_NOT_FOUND'], [$error->getCode(), $error->getMessage()]);
            }
        }
    }

    public function testMessagesOfOneConversationAreLimitedPerHour(): void
    {
        $created = $this->ask()->execute(new AskGalleryQuestionInputDto(self::TOKEN, 'Мария', 'Первый'), new IdempotencyKey(self::KEY));
        for ($index = 1; $index < QuestionMessageRecorder::MESSAGES_PER_HOUR; ++$index) {
            $this->add()->execute($created->questionKey, 'Ещё ' . $index, new IdempotencyKey(sprintf('%032x', $index)));
        }

        $this->expectExceptionObject(new HttpException('RATE_LIMITED', 429));
        $this->add()->execute($created->questionKey, 'Лишнее', new IdempotencyKey(sprintf('%032x', 999)));
    }

    public function testStaffConversationIsCreatedOnceAndKeepsCurrentContext(): void
    {
        $role = 'teacher';
        $staff = new class($role) implements StaffQuestionContextInterface {
            public function __construct(public string $role) {}

            public function resolve(int $userId): StaffQuestionContextDto
            {
                return new StaffQuestionContextDto($userId, 'Ольга Петрова', $this->role, ['Солнышко']);
            }
        };
        $use = new AddStaffQuestionMessageUseCase(
            $staff,
            new ImmediateTransaction(),
            $this->questions,
            new QuestionMessageRecorder($this->questions),
            new QuestionTextPolicy(),
            new MaxQuestionTextBuilder(),
            new QuestionHistory($this->questions),
            $this->publisher,
            $this->clock,
        );
        $get = new GetStaffQuestionUseCase($staff, $this->questions, new QuestionHistory($this->questions));

        self::assertNull($get->execute(7)->id);
        $use->execute(7, 'Можно перенести съёмку?', new IdempotencyKey(self::KEY));
        $staff->role = 'head';
        $output = $use->execute(7, 'И ещё вопрос', new IdempotencyKey(str_repeat('d', 32)));

        self::assertCount(1, $this->questions->questions);
        self::assertSame('Заведующая · «Солнышко»', $this->questions->questions[100]['context']);
        self::assertSame(['staff', 'staff'], array_map(static fn($message): string => $message->author, $output->messages));
        self::assertSame(100, $get->execute(7)->id);
    }

    /** @param null|\Closure(): never $failure */
    private function ask(?\Closure $failure = null): AskGalleryQuestionUseCase
    {
        $galleries = new class($failure) implements GalleryQuestionContextInterface {
            public function __construct(private ?\Closure $failure) {}

            public function resolve(string $galleryToken): GalleryQuestionContextDto
            {
                if (null !== $this->failure) {
                    ($this->failure)();
                }

                return new GalleryQuestionContextDto(42, 'Солнышко', 'Пчёлки', 'Рита');
            }
        };

        return new AskGalleryQuestionUseCase(
            $galleries,
            new ImmediateTransaction(),
            $this->questions,
            new QuestionMessageRecorder($this->questions),
            new QuestionKeySeal(),
            new QuestionTextPolicy(),
            new MaxQuestionTextBuilder(),
            new QuestionHistory($this->questions),
            $this->publisher,
            $this->clock,
        );
    }

    private function add(): AddGalleryQuestionMessageUseCase
    {
        return new AddGalleryQuestionMessageUseCase(
            new ParentQuestionAccess($this->questions),
            new ImmediateTransaction(),
            $this->questions,
            new QuestionMessageRecorder($this->questions),
            new QuestionTextPolicy(),
            new QuestionHistory($this->questions),
            $this->publisher,
            $this->clock,
        );
    }
}
