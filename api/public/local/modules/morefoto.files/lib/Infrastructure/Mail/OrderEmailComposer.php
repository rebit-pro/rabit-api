<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Mail;

use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Delivery\Dto\OrderEmailTextOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

/** Короткое письмо родителю: номер оплаченного заказа, кнопка-ссылка на страницу заказа и срок скачивания по Москве. */
final readonly class OrderEmailComposer implements OrderEmailComposerInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(private string $appUrl) {}

    public function compose(OrderEmailOutputDto $order, string $orderKey, \DateTimeImmutable $expiresAt): OrderEmailTextOutputDto
    {
        $url = rtrim($this->appUrl, '/') . '/orders/access/' . $orderKey;
        $until = $expiresAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('d.m.Y H:i');
        $number = '' === $order->number ? '' : ' №' . $order->number;
        $body = implode("\n", [
            'Здравствуйте!',
            '',
            'Заказ' . $number . ' оплачен. По ссылке — ваш заказ: электронные фотографии можно скачать до ' . $until . ' (МСК).',
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
            . '<p>Заказ' . $e($number) . ' оплачен. Электронные фотографии можно скачать до ' . $e($until) . ' (МСК).</p>'
            . '<p><a href="' . $e($url) . '" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#24658A;color:#ffffff;text-decoration:none">Открыть заказ</a></p>'
            . '<p style="color:#5b6670">Если кнопка не открывается, скопируйте адрес: ' . $e($url) . '</p>'
            . '<p>Печатные фотографии из заказа передадим в детский сад или школу.<br>Ссылка открывает заказ без регистрации — не пересылайте её посторонним.</p>'
            . '<p>Море фото</p>';

        return new OrderEmailTextOutputDto('Заказ' . $number . ' оплачен — ссылка на ваши фото', $body, $html);
    }
}
