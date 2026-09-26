<?php

declare(strict_types=1);

namespace Morefoto\Legal\Infrastructure\Seller;

use Morefoto\Legal\Application\Document\Contract\SellerProviderInterface;
use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;

/** Реквизиты продавца из окружения сервера: в репозиторий они не попадают. Пустое значение считается незаданным. */
final readonly class ConfiguredSellerProvider implements SellerProviderInterface
{
    /** @param array{name: false|string, inn: false|string, ogrnip: false|string, address: false|string, email: false|string, phone: false|string} $values */
    public function __construct(private array $values) {}

    public function seller(): SellerOutputDto
    {
        $name = $this->value('name');
        $inn = $this->value('inn');
        $ogrnip = $this->value('ogrnip');
        $address = $this->value('address');
        $email = $this->value('email');

        return new SellerOutputDto(
            published: null !== $name && null !== $inn && null !== $ogrnip && null !== $address && null !== $email,
            name: $name,
            inn: $inn,
            ogrnip: $ogrnip,
            address: $address,
            email: $email,
            phone: $this->value('phone'),
        );
    }

    private function value(string $key): ?string
    {
        $value = trim((string)$this->values[$key]);

        return '' === $value ? null : $value;
    }
}
