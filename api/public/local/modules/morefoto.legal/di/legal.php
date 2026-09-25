<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Legal\Application\Consent\Service\ConsentRecorder;
use Morefoto\Legal\Application\Consent\UseCase\AcceptStaffConsentsUseCase;
use Morefoto\Legal\Application\Consent\UseCase\GetPendingStaffConsentsUseCase;
use Morefoto\Legal\Application\Document\Contract\SellerProviderInterface;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Application\Document\Service\LegalTextRenderer;
use Morefoto\Legal\Application\Document\UseCase\GetLegalDocumentUseCase;
use Morefoto\Legal\Application\Document\UseCase\ListLegalDocumentsUseCase;
use Morefoto\Legal\Domain\Consent\Repository\ConsentJournalInterface;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use Morefoto\Legal\Domain\Document\Service\ConsentRequirementPolicy;
use Morefoto\Legal\Domain\Document\Service\ReconsentPolicy;
use Morefoto\Legal\Infrastructure\Consent\BitrixConsentJournal;
use Morefoto\Legal\Infrastructure\Document\FileLegalDocumentCatalog;
use Morefoto\Legal\Infrastructure\Seller\ConfiguredSellerProvider;
use Morefoto\Legal\Presentation\Controller\LegalDocumentController;
use Morefoto\Legal\Presentation\Controller\StaffConsentController;
use Morefoto\Legal\Presentation\LegalResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Presentation\Consent\AcceptedDocumentInputMapper;

$services = [
    LegalDocumentCatalogInterface::class => [
        'constructor' => static fn(): LegalDocumentCatalogInterface => new FileLegalDocumentCatalog(dirname(__DIR__) . '/documents'),
    ],
    // Requisites of the individual entrepreneur live only in the server environment, never in the repository.
    SellerProviderInterface::class => [
        'constructor' => static fn(): SellerProviderInterface => new ConfiguredSellerProvider([
            'name' => getenv('MOREFOTO_SELLER_NAME'),
            'inn' => getenv('MOREFOTO_SELLER_INN'),
            'ogrnip' => getenv('MOREFOTO_SELLER_OGRNIP'),
            'address' => getenv('MOREFOTO_SELLER_ADDRESS'),
            'email' => getenv('MOREFOTO_SELLER_EMAIL'),
            'phone' => getenv('MOREFOTO_SELLER_PHONE'),
        ]),
    ],
    ConsentJournalInterface::class => [
        'constructor' => static fn(): ConsentJournalInterface => new BitrixConsentJournal(),
    ],
    ConsentRecorderInterface::class => [
        'constructor' => static fn(): ConsentRecorderInterface => ServiceLocator::getInstance()->get(ConsentRecorder::class),
    ],
    ConsentRequirementPolicy::class => ['className' => ConsentRequirementPolicy::class],
    ReconsentPolicy::class => ['className' => ReconsentPolicy::class],
    LegalTextRenderer::class => ['className' => LegalTextRenderer::class],
    LegalDocumentMapper::class => ['className' => LegalDocumentMapper::class],
    LegalResultMapper::class => ['className' => LegalResultMapper::class],
];
$dependencies = [
    ConsentRecorder::class => [LegalDocumentCatalogInterface::class, ConsentRequirementPolicy::class, ConsentJournalInterface::class, ClockInterface::class],
    ListLegalDocumentsUseCase::class => [LegalDocumentCatalogInterface::class, SellerProviderInterface::class, LegalDocumentMapper::class],
    GetLegalDocumentUseCase::class => [LegalDocumentCatalogInterface::class, SellerProviderInterface::class, LegalTextRenderer::class, LegalDocumentMapper::class],
    GetPendingStaffConsentsUseCase::class => [ConsentRecorder::class, ConsentJournalInterface::class, ReconsentPolicy::class, LegalDocumentMapper::class],
    AcceptStaffConsentsUseCase::class => [ConsentRecorder::class, GetPendingStaffConsentsUseCase::class],
    LegalDocumentController::class => [ListLegalDocumentsUseCase::class, GetLegalDocumentUseCase::class, LegalResultMapper::class],
    StaffConsentController::class => [GetPendingStaffConsentsUseCase::class, AcceptStaffConsentsUseCase::class, AcceptedDocumentInputMapper::class, LegalResultMapper::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
