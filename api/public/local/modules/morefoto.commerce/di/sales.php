<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Mapper\SalesReportRowMapper;
use Morefoto\Commerce\Application\Sales\Service\SalesReportScope;
use Morefoto\Commerce\Application\Sales\UseCase\ExportSalesReportPdfUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetChildPreviewUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetGroupSalesUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetSalesReportUseCase;
use Morefoto\Commerce\Domain\Sales\Repository\GroupSalesRepository;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;
use Morefoto\Commerce\Domain\Sales\Service\ChildSalesStatusPolicy;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\MpdfSalesReportWriter;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\SalesReportHtml;
use Morefoto\Commerce\Infrastructure\Sales\SalesAccess;
use Morefoto\Commerce\Presentation\Controller\GroupSalesController;
use Morefoto\Commerce\Presentation\Controller\SalesReportController;
use Morefoto\Commerce\Presentation\Sales\SalesReportResultMapper;
use Morefoto\Commerce\Presentation\Sales\SalesResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

return [
    SalesAccessInterface::class => [
        'constructor' => static fn(): SalesAccessInterface => new SalesAccess(ServiceLocator::getInstance()->get(StaffRequestAccessInterface::class)),
    ],
    GroupSalesRepository::class => ['className' => GroupSalesRepository::class],
    SalesResultMapper::class => ['className' => SalesResultMapper::class],
    GetGroupSalesUseCase::class => [
        'className' => GetGroupSalesUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SalesAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(GroupSalesRepository::class),
        ],
    ],
    GroupSalesController::class => [
        'className' => GroupSalesController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetGroupSalesUseCase::class),
            ServiceLocator::getInstance()->get(SalesResultMapper::class),
        ],
    ],
    SalesReportRepository::class => ['className' => SalesReportRepository::class],
    ChildSalesStatusPolicy::class => ['className' => ChildSalesStatusPolicy::class],
    SalesReportRowMapper::class => [
        'className' => SalesReportRowMapper::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ChildSalesStatusPolicy::class),
        ],
    ],
    SalesReportScope::class => [
        'className' => SalesReportScope::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SalesAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
        ],
    ],
    GetSalesReportUseCase::class => [
        'className' => GetSalesReportUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SalesReportScope::class),
            ServiceLocator::getInstance()->get(SalesReportRepository::class),
            ServiceLocator::getInstance()->get(GroupChildrenInterface::class),
            ServiceLocator::getInstance()->get(SalesReportRowMapper::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    SalesReportPdfWriterInterface::class => [
        'constructor' => static function(): SalesReportPdfWriterInterface {
            $temp = (string)getenv('MOREFOTO_PDF_TEMP_PATH');

            return new MpdfSalesReportWriter(
                new SalesReportHtml(),
                dirname(__DIR__) . '/resources/pdf/fonts',
                '' === $temp ? sys_get_temp_dir() . '/morefoto-mpdf' : $temp,
            );
        },
    ],
    ExportSalesReportPdfUseCase::class => [
        'className' => ExportSalesReportPdfUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetSalesReportUseCase::class),
            ServiceLocator::getInstance()->get(GroupChildrenInterface::class),
            ServiceLocator::getInstance()->get(SalesReportPdfWriterInterface::class),
        ],
    ],
    GetChildPreviewUseCase::class => [
        'className' => GetChildPreviewUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SalesAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(GroupChildrenInterface::class),
        ],
    ],
    SalesReportResultMapper::class => ['className' => SalesReportResultMapper::class],
    SalesReportController::class => [
        'className' => SalesReportController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetSalesReportUseCase::class),
            ServiceLocator::getInstance()->get(ExportSalesReportPdfUseCase::class),
            ServiceLocator::getInstance()->get(GetChildPreviewUseCase::class),
            ServiceLocator::getInstance()->get(SalesReportResultMapper::class),
        ],
    ],
];
