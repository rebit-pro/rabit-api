<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderEmailLinkOutputDto
{
    /**
     * @param string $key       ключ страницы заказа `/orders/access/<key>`, 64 hex; нигде не хранится открытым
     * @param string $expiresAt конец действия ключа — конец месяца файлов, UTC `Y-m-d H:i:s`
     */
    public function __construct(
        public string $key,
        public string $expiresAt,
    ) {}
}
