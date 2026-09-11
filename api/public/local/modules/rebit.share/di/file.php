<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Rebit\Share\Application\UseCase\UploadFileUseCase;
use Rebit\Share\Domain\File\Service\FileUploadService;
use Rebit\Share\Domain\File\Service\UploadedFileOwnershipService;
use Rebit\Share\Presentation\Controller\FileController;

return [
    UploadedFileOwnershipService::class => [
        'constructor' => static function(): UploadedFileOwnershipService {
            return new UploadedFileOwnershipService(
                Application::getInstance()->getManagedCache(),
            );
        },
    ],
    FileUploadService::class => [
        'className' => FileUploadService::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UploadedFileOwnershipService::class),
        ],
    ],
    UploadFileUseCase::class => [
        'className' => UploadFileUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(FileUploadService::class),
        ],
    ],
    FileController::class => [
        'className' => FileController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UploadFileUseCase::class),
        ],
    ],
];
