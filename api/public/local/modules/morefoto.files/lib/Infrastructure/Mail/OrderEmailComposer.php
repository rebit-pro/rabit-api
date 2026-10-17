<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Mail;

use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Delivery\Dto\OrderEmailTextOutputDto;
use Morefoto\Files\Application\Delivery\Dto\OrderLetterInputDto;
use Morefoto\Files\Domain\Delivery\Service\AttachmentPlanner;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

/**
 * Письмо родителю: номер оплаченного заказа, какие фото во вложении («письмо 2 из 5: фото 3–4 из 10»), кадры, которые
 * не помещаются в письмо, кнопка-ссылка на страницу заказа и срок скачивания по Москве. Письмо без вложений — как в J2.
 */
final readonly class OrderEmailComposer implements OrderEmailComposerInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(private string $appUrl) {}

    public function compose(OrderEmailOutputDto $order, string $orderKey, \DateTimeImmutable $expiresAt, OrderLetterInputDto $letter): OrderEmailTextOutputDto
    {
        $url = rtrim($this->appUrl, '/') . '/orders/access/' . $orderKey;
        $until = $expiresAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('d.m.Y H:i');
        $number = '' === $order->number ? '' : ' №' . $order->number;
        $lines = [$this->intro($number, $until, $letter)];
        if ([] !== $letter->linkOnly) {
            $lines[] = 'Слишком большие для письма (больше ' . intdiv(AttachmentPlanner::FILE_BYTES, 1024 * 1024) . ' МБ): '
                . implode(', ', $letter->linkOnly) . ' — скачайте их по ссылке.';
        }
        $body = implode("\n", [
            'Здравствуйте!',
            '',
            ...$lines,
            '',
            $url,
            '',
            'Печатные фотографии из заказа передадим в детский сад или школу.',
            'Ссылка открывает заказ без регистрации — не пересылайте её посторонним.',
            '',
            'Море фото',
        ]);
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = '<p>Здравствуйте!</p>'
            . implode('', array_map(static fn(string $line): string => '<p>' . $e($line) . '</p>', $lines))
            . '<p><a href="' . $e($url) . '" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#24658A;color:#ffffff;text-decoration:none">Открыть заказ</a></p>'
            . '<p style="color:#5b6670">Если кнопка не открывается, скопируйте адрес: ' . $e($url) . '</p>'
            . '<p>Печатные фотографии из заказа передадим в детский сад или школу.<br>Ссылка открывает заказ без регистрации — не пересылайте её посторонним.</p>'
            . '<p>Море фото</p>';

        return new OrderEmailTextOutputDto('Заказ' . $number . ' оплачен — ' . $this->topic($letter), $body, $html);
    }

    private function intro(string $number, string $until, OrderLetterInputDto $letter): string
    {
        $count = count($letter->attachments);
        if (0 === $count) {
            return 'Заказ' . $number . ' оплачен. По ссылке — ваш заказ: электронные фотографии можно скачать до ' . $until . ' (МСК).';
        }
        $last = $letter->first + $count - 1;
        $range = 'фото ' . $letter->first . ($last === $letter->first ? '' : '–' . $last) . ' из ' . $letter->photos;
        $content = match (true) {
            1 < $letter->letters => 'Письмо ' . $letter->number . ' из ' . $letter->letters . ': во вложении ' . $range . '.',
            $count === $letter->photos => 'Во вложении — все фото заказа (' . $count . ').',
            default => 'Во вложении ' . $range . '.',
        };

        return 'Заказ' . $number . ' оплачен. ' . $content . ' Все фото можно скачать по ссылке до ' . $until . ' (МСК).';
    }

    private function topic(OrderLetterInputDto $letter): string
    {
        return match (true) {
            [] === $letter->attachments => 'ссылка на ваши фото',
            1 < $letter->letters => 'ваши фото, письмо ' . $letter->number . ' из ' . $letter->letters,
            default => 'ваши фото',
        };
    }
}
