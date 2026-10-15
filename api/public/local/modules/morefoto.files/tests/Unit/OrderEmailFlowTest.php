<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Infrastructure\Mail\OrderEmailComposer;
use Morefoto\Files\Tests\Unit\Support\DeliveryFixture;
use Morefoto\Files\Tests\Unit\Support\FilesFixture;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J2-T01…T05: письмо родителю со ссылкой на оплаченный заказ — диспетчер, выключенная отправка, повтор, лимиты и статусы.
 *
 * @internal
 */
final class OrderEmailFlowTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testDispatcherSendsOneLetterPerPaidOrderAndRepeatsNothing(): void
    {
        $f = new DeliveryFixture();
        $first = $f->dispatch()->execute(100);
        self::assertSame([1, 1, 0], [$first->created, $first->queued, $first->cancelled]);
        $second = $f->dispatch()->execute(100);
        self::assertSame([0, 0, 0], [$second->created, $second->queued, $second->cancelled]);

        self::assertCount(1, $f->queued);
        $letter = $f->queued[0];
        self::assertSame(['files-order-email', DeliveryFixture::EMAIL, 10], [$letter->consumer, $letter->recipient, $letter->maxAttempts]);
        self::assertMatchesRegularExpression('/^order-email:[a-f0-9-]{36}$/D', $letter->deduplicationKey);
        self::assertStringContainsString('https://app.example.test/orders/access/' . DeliveryFixture::LINK_KEY, $letter->body);
        self::assertStringContainsString('Заказ №MF-0007 оплачен', $letter->body);
        self::assertStringContainsString('28.02.2027 10:00 (МСК)', $letter->body);
        self::assertSame('2027-02-01 10:00:00', $f->deliveries->cursorAt->format('Y-m-d H:i:s'));
        self::assertSame(7, $f->deliveries->cursorOrder);
        $row = array_values($f->deliveries->rows)[0];
        self::assertSame([DeliveryKindEnum::AUTO, DeliveryStatusEnum::QUEUED], [$row->kind, $row->status]);
    }

    public function testDisabledSendingKeepsLettersUntilItIsSwitchedOn(): void
    {
        $f = new DeliveryFixture();
        $f->enabled = false;
        self::assertSame([1, 0], [$f->dispatch()->execute(100)->created, count($f->queued)]);
        self::assertSame(DeliveryStatusEnum::HELD, array_values($f->deliveries->rows)[0]->status);
        $this->expectHttp('DELIVERY_DISABLED', 409, static fn() => $f->resend()->execute(FilesFixture::KEY, self::KEY));
        self::assertFalse($f->summary()->execute(FilesFixture::KEY)->enabled);

        $f->enabled = true;
        self::assertSame(1, $f->dispatch()->execute(100)->queued);
        self::assertCount(1, $f->queued);
    }

    public function testExpiredFilesOrUnpaidOrderGetNoLetter(): void
    {
        $f = new DeliveryFixture();
        $f->files->now = '2027-03-01 00:00:00';
        $run = $f->dispatch()->execute(100);
        self::assertSame([1, 0, 1], [$run->created, $run->queued, $run->cancelled]);
        self::assertSame([], $f->queued);
        self::assertSame(DeliveryStatusEnum::CANCELLED, array_values($f->deliveries->rows)[0]->status);
        $this->expectHttp('FILES_UNAVAILABLE', 409, static fn() => $f->resend()->execute(FilesFixture::KEY, self::KEY));

        $g = new DeliveryFixture();
        $g->paid = false;
        self::assertSame(0, $g->dispatch()->execute(100)->created);
        $summary = $g->summary()->execute(FilesFixture::KEY);
        self::assertSame([null, false], [$summary->recipient, $summary->canResend]);
    }

    public function testRepeatKeepsTheSameLinkWaitsTenMinutesAndStopsAtFivePerDay(): void
    {
        $f = new DeliveryFixture();
        $f->dispatch()->execute(100);
        $summary = $f->summary()->execute(FilesFixture::KEY);
        self::assertSame([true, DeliveryFixture::EMAIL, 'pending', true], [$summary->enabled, $summary->recipient, $summary->latest?->status, $summary->canResend]);
        self::assertSame('2027-02-10 09:10:00', $summary->resendAvailableAt?->format('Y-m-d H:i:s'));
        $this->expectHttp('DELIVERY_TOO_SOON', 429, static fn() => $f->resend()->execute(FilesFixture::KEY, self::KEY));

        $f->files->now = '2027-02-10 09:10:00';
        $again = $f->resend()->execute(FilesFixture::KEY, self::KEY);
        self::assertSame(['manual', 'pending'], [$again->kind, $again->status]);
        self::assertSame($again->id, $f->resend()->execute(FilesFixture::KEY, self::KEY)->id, 'Тот же Idempotency-Key — то же письмо.');
        self::assertCount(2, $f->queued);
        self::assertSame($f->queued[0]->body, $f->queued[1]->body, 'Повтор несёт ту же ссылку: открытая по первому письму страница не закрывается.');

        foreach (['09:20', '09:30', '09:40'] as $index => $time) {
            $f->files->now = '2027-02-10 ' . $time . ':00';
            $f->resend()->execute(FilesFixture::KEY, str_repeat((string)($index + 1), 32));
        }
        $f->files->now = '2027-02-10 09:50:00';
        $this->expectHttp('DELIVERY_LIMIT', 429, static fn() => $f->resend()->execute(FilesFixture::KEY, str_repeat('9', 32)));
        $f->files->now = '2027-02-11 09:05:00';
        self::assertSame('manual', $f->resend()->execute(FilesFixture::KEY, str_repeat('8', 32))->kind, 'Через сутки лимит освобождается.');
    }

    public function testStatusFollowsTheNotificationAndHidesForeignLetters(): void
    {
        $f = new DeliveryFixture();
        $f->dispatch()->execute(100);
        $delivery = array_values($f->deliveries->rows)[0];
        self::assertSame('pending', $f->delivery()->execute(FilesFixture::KEY, $delivery->publicId)->status);
        $f->statuses[(string)$delivery->operationId] = 'accepted';
        $accepted = $f->delivery()->execute(FilesFixture::KEY, $delivery->publicId);
        self::assertSame(['accepted', '2027-02-10 09:01:00'], [$accepted->status, $accepted->acceptedAt?->format('Y-m-d H:i:s')]);
        $f->statuses[(string)$delivery->operationId] = 'failed';
        self::assertSame('failed', $f->summary()->execute(FilesFixture::KEY)->latest?->status);
        $f->statuses[(string)$delivery->operationId] = 'retryWait';
        self::assertSame('pending', $f->delivery()->execute(FilesFixture::KEY, $delivery->publicId)->status);

        $this->expectHttp('DELIVERY_NOT_FOUND', 404, static fn() => $f->delivery()->execute(FilesFixture::KEY, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'));
        $this->expectHttp('ORDER_NOT_FOUND', 404, static fn() => $f->delivery()->execute(str_repeat('f', 64), $delivery->publicId));
    }

    public function testLetterEscapesHtmlAndShowsMoscowTime(): void
    {
        $text = new OrderEmailComposer('https://app.morefoto36.ru')->compose(
            new OrderEmailOutputDto(7, FilesFixture::ORDER, 'MF<1>', 'mama@example.test', '2027-01-31 07:00:00', '2027-01-31 07:00:01'),
            DeliveryFixture::LINK_KEY,
            new \DateTimeImmutable('2027-02-28 07:00:00', new \DateTimeZone('UTC')),
        );

        self::assertSame('Заказ №MF<1> оплачен — ссылка на ваши фото', $text->subject);
        self::assertStringContainsString('Заказ №MF&lt;1&gt; оплачен', $text->html);
        self::assertStringContainsString('href="https://app.morefoto36.ru/orders/access/' . DeliveryFixture::LINK_KEY . '"', $text->html);
        self::assertStringContainsString('до 28.02.2027 10:00 (МСК)', $text->body);
        self::assertStringNotContainsString('<script', $text->html);
    }

    private function expectHttp(string $code, int $status, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame([$code, $status], [$error->getMessage(), $error->getCode()]);
        }
    }
}
