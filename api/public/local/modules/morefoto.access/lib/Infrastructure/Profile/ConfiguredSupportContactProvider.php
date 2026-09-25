<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Profile;

use Morefoto\Access\Application\Profile\Contract\SupportContactProviderInterface;
use Morefoto\Access\Application\Profile\Dto\SupportContactOutputDto;

/** Contact from the module configuration (MOREFOTO_SUPPORT_NAME, _EMAIL, _PHONE); empty values are not shown. */
final readonly class ConfiguredSupportContactProvider implements SupportContactProviderInterface
{
    public function __construct(
        private string $name,
        private string $email,
        private string $phone,
    ) {}

    public function contact(): ?SupportContactOutputDto
    {
        $name = $this->value($this->name);
        $email = $this->value($this->email);
        $phone = $this->value($this->phone);
        if (null === $name && null === $email && null === $phone) {
            return null;
        }

        return new SupportContactOutputDto(name: $name, email: $email, phone: $phone);
    }

    private function value(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
