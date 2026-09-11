<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Dto\Request;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Заявка с формы сайта.
 *
 * Поле company — honeypot: скрыто от пользователей, заполняется только ботами.
 * Поле page — служебное: адрес страницы, с которой пришла заявка.
 * Поле source — какая форма отправила заявку (dialog, estimate, ...).
 */
final readonly class SubmitLeadRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя.')]
        #[Assert\Length(min: 2, max: 120, minMessage: 'Имя слишком короткое.')]
        public string $name,
        #[Assert\NotBlank(message: 'Укажите телефон.')]
        #[Assert\Length(min: 6, max: 40)]
        #[Assert\Regex(pattern: '/^[+\d][\d\s()\-]{5,}$/', message: 'Проверьте номер телефона.')]
        public string $phone,
        #[Assert\NotBlank(message: 'Опишите задачу.')]
        #[Assert\Length(min: 10, max: 3000, minMessage: 'Опишите задачу подробнее.')]
        public string $description,
        #[Assert\Email(message: 'Проверьте email.')]
        #[Assert\Length(max: 180)]
        public string $email = '',
        public string $company = '',
        public string $page = '',
        #[Assert\Length(max: 40)]
        public string $source = '',
    ) {}
}
