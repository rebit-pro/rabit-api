<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\CalendarCommandValidator;
use Morefoto\Organization\Application\Calendar\Service\GroupCalendar;
use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Infrastructure\Calendar\ServerCalendarClock;
use Morefoto\Organization\Infrastructure\Handoff\GroupDirectory;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

return [
    CalendarClockInterface::class => ['constructor' => static fn(): CalendarClockInterface => new ServerCalendarClock()],
    CalendarCommandValidator::class => ['className' => CalendarCommandValidator::class],
    GroupCalendarRepository::class => ['className' => GroupCalendarRepository::class],
    GroupCalendarInterface::class => [
        'constructor' => static fn(): GroupCalendarInterface => new GroupCalendar(
            ServiceLocator::getInstance()->get(GroupCalendarRepository::class),
            ServiceLocator::getInstance()->get(InstitutionOperationRepository::class),
            ServiceLocator::getInstance()->get(CalendarClockInterface::class),
            ServiceLocator::getInstance()->get(CalendarCommandValidator::class),
        ),
    ],
    GroupDirectoryInterface::class => [
        'constructor' => static fn(): GroupDirectoryInterface => new GroupDirectory(
            ServiceLocator::getInstance()->get(CalendarClockInterface::class),
        ),
    ],
    ChangeGroupCalendarUseCase::class => [
        'className' => ChangeGroupCalendarUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupCalendarInterface::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(InstitutionTransactionInterface::class),
            ServiceLocator::getInstance()->get(CalendarCommandValidator::class),
        ],
    ],
];
