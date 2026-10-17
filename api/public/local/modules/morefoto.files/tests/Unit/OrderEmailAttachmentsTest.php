<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Application\Delivery\Dto\OrderLetterInputDto;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Infrastructure\Mail\OrderEmailComposer;
use Morefoto\Files\Tests\Unit\Support\DeliveryFixture;
use Morefoto\Files\Tests\Unit\Support\FilesFixture;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Notification\Dto\EmailAttachmentInputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J5-T03…T06: оригиналы права заказа во вложениях — план писем пишется один раз, падение между письмами повторяет только
 * неотправленное, тексты «письмо k из n», кадры только по ссылке и статус отправки по всем письмам.
 *
 * @internal
 */
final class OrderEmailAttachmentsTest extends TestCase
{
    private const int MB = 1024 * 1024;

    public function testThreeFramesOfEightMegabytesGoInTwoLettersPlannedOnce(): void
    {
        $f = $this->fixture(8, 8, 8);
        $f->dispatch()->execute(100);
        $f->dispatch()->execute(100);

        self::assertSame(1, $f->deliveries->plans);
        self::assertCount(2, $f->queued);
        $delivery = array_values($f->deliveries->rows)[0];
        self::assertSame([DeliveryStatusEnum::QUEUED, 2], [$delivery->status, $delivery->letters]);
        self::assertSame($f->operations['order-email:' . $delivery->publicId . ':1'], $delivery->operationId, 'OPERATION_ID — первое письмо.');
        self::assertSame(
            [['order-email:' . $delivery->publicId . ':1', ['AB001.jpg', 'CD001.jpg']], ['order-email:' . $delivery->publicId . ':2', ['CD002.jpg']]],
            array_map(static fn($letter): array => [$letter->deduplicationKey, array_map(static fn(EmailAttachmentInputDto $file): string => $file->name, $letter->attachments)], $f->queued),
        );
        self::assertEquals(
            new EmailAttachmentInputDto('AB001.jpg', 'image/jpeg', '/nonexistent/' . FilesFixture::P1 . '.jpg', 8 * self::MB),
            $f->queued[0]->attachments[0],
            'Вложение — ссылка на оригинал с его размером, не байты.',
        );
        self::assertSame('Заказ №MF-0007 оплачен — ваши фото, письмо 1 из 2', $f->queued[0]->subject);
        self::assertStringContainsString('Письмо 1 из 2: во вложении фото 1–2 из 3. Все фото можно скачать по ссылке до 28.02.2027 10:00 (МСК).', $f->queued[0]->body);
        self::assertStringContainsString('Письмо 2 из 2: во вложении фото 3 из 3.', $f->queued[1]->body);
        foreach ($f->queued as $letter) {
            self::assertStringContainsString('https://app.example.test/orders/access/' . DeliveryFixture::LINK_KEY, $letter->body, 'Ссылка на заказ — в каждом письме.');
        }
    }

    public function testAFailureBetweenLettersRepeatsOnlyTheLetterThatIsNotQueued(): void
    {
        $f = $this->fixture(8, 8, 8);
        $id = 'dddddddd-dddd-4ddd-8ddd-000000000001';
        $f->failOn = 'order-email:' . $id . ':2';
        try {
            $f->dispatch()->execute(100);
            self::fail('H1 storage failure must stop the run.');
        } catch (\RuntimeException) {
        }
        self::assertSame(DeliveryStatusEnum::HELD, $f->deliveries->rows[$id]->status);
        self::assertSame([true, false], array_map(static fn($part): bool => null !== $part->operationId, $f->deliveries->parts($id)));

        $f->dispatch()->execute(100);
        self::assertSame(DeliveryStatusEnum::QUEUED, $f->deliveries->rows[$id]->status);
        self::assertSame(1, $f->deliveries->plans, 'Повтор берёт записанный план.');
        self::assertSame(3, $f->calls, 'Письмо 1 не ставится повторно: 1, сбой 2, затем только 2.');
        self::assertSame(['order-email:' . $id . ':1', 'order-email:' . $id . ':2'], array_map(static fn($letter): string => $letter->deduplicationKey, $f->queued));
    }

    public function testAFrameAboveTheLetterLimitIsNamedAndStaysByTheLink(): void
    {
        $f = $this->fixture(8, 8, 25);
        $f->dispatch()->execute(100);

        self::assertCount(1, $f->queued);
        self::assertSame(['AB001.jpg', 'CD001.jpg'], array_map(static fn(EmailAttachmentInputDto $file): string => $file->name, $f->queued[0]->attachments));
        self::assertSame('Заказ №MF-0007 оплачен — ваши фото', $f->queued[0]->subject);
        self::assertStringContainsString('Во вложении фото 1–2 из 3.', $f->queued[0]->body);
        self::assertStringContainsString('Слишком большие для письма (больше 20 МБ): CD002.jpg — скачайте их по ссылке.', $f->queued[0]->body);
        $latest = $f->summary()->execute(FilesFixture::KEY)->latest;
        self::assertSame([1, 0, 2, 1], [$latest?->letters, $latest?->acceptedLetters, $latest?->photos, $latest?->linkOnly]);
    }

    public function testAllFramesInOneLetterAndAnOrderWithoutFilesKeepTheLinkLetter(): void
    {
        $f = $this->fixture(1, 1, 1);
        $f->dispatch()->execute(100);
        self::assertSame('Заказ №MF-0007 оплачен — ваши фото', $f->queued[0]->subject);
        self::assertStringContainsString('Заказ №MF-0007 оплачен. Во вложении — все фото заказа (3).', $f->queued[0]->body);

        $composer = new OrderEmailComposer('https://app.example.test');
        $order = new OrderEmailOutputDto(7, FilesFixture::ORDER, 'MF-0007', DeliveryFixture::EMAIL, '2027-01-31 07:00:00', '2027-02-01 10:00:00');
        $until = new \DateTimeImmutable('2027-02-28 07:00:00', new \DateTimeZone('UTC'));
        $link = $composer->compose($order, DeliveryFixture::LINK_KEY, $until, new OrderLetterInputDto(1, 1, [], 1, 0, []));
        self::assertSame('Заказ №MF-0007 оплачен — ссылка на ваши фото', $link->subject);
        self::assertStringContainsString('По ссылке — ваш заказ: электронные фотографии можно скачать до 28.02.2027 10:00 (МСК).', $link->body);
        $tooLarge = $composer->compose($order, DeliveryFixture::LINK_KEY, $until, new OrderLetterInputDto(1, 1, [], 1, 1, ['<b>.jpg']));
        self::assertStringContainsString('Слишком большие для письма (больше 20 МБ): &lt;b&gt;.jpg', $tooLarge->html);
    }

    public function testTheStatusCountsEveryLetter(): void
    {
        $f = $this->fixture(8, 8, 8);
        $f->dispatch()->execute(100);
        $delivery = array_values($f->deliveries->rows)[0];
        [$first, $second] = array_values($f->operations);
        $view = static fn() => $f->delivery()->execute(FilesFixture::KEY, $delivery->publicId);
        self::assertSame(['pending', 2, 0, 3, 0], [$view()->status, $view()->letters, $view()->acceptedLetters, $view()->photos, $view()->linkOnly]);

        $f->statuses[$first] = 'accepted';
        self::assertSame(['pending', 1, null], [$view()->status, $view()->acceptedLetters, $view()->acceptedAt]);
        $f->statuses[$second] = 'accepted';
        self::assertSame(['accepted', 2, '2027-02-10 09:01:00'], [$view()->status, $view()->acceptedLetters, $view()->acceptedAt?->format('Y-m-d H:i:s')]);
        $f->statuses[$second] = 'failed';
        self::assertSame(['failed', 1], [$view()->status, $view()->acceptedLetters]);
        $f->statuses[$second] = 'retryWait';
        self::assertSame('pending', $view()->status);
    }

    public function testTheRepeatSendsEveryLetterAgain(): void
    {
        $f = $this->fixture(8, 8, 8);
        $f->dispatch()->execute(100);
        $f->files->now = '2027-02-10 09:10:00';
        $again = $f->resend()->execute(FilesFixture::KEY, '0123456789abcdef0123456789abcdef');

        self::assertSame(['manual', 'pending', 2], [$again->kind, $again->status, $again->letters]);
        self::assertCount(4, $f->queued);
        self::assertEquals(
            array_map(static fn($letter): array => [$letter->subject, $letter->body, $letter->attachments], array_slice($f->queued, 0, 2)),
            array_map(static fn($letter): array => [$letter->subject, $letter->body, $letter->attachments], array_slice($f->queued, 2)),
            'Повтор (J5-DEC-03) пересылает все письма с тем же составом.',
        );
    }

    private function fixture(int ...$megabytes): DeliveryFixture
    {
        $f = new DeliveryFixture();
        $f->files->withOriginals(FilesFixture::P1, FilesFixture::P2, FilesFixture::P3);
        foreach ([FilesFixture::P1, FilesFixture::P2, FilesFixture::P3] as $index => $photo) {
            $f->files->sizes[$photo] = $megabytes[$index] * self::MB;
        }

        return $f;
    }
}
