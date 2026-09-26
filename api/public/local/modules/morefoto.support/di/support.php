<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Support\Application\Max\UseCase\GetMaxStatusUseCase;
use Morefoto\Support\Application\Max\UseCase\HandleMaxUpdateUseCase;
use Morefoto\Support\Application\Max\UseCase\SubscribeMaxWebhookUseCase;
use Morefoto\Support\Application\Question\Contract\GalleryQuestionContextInterface;
use Morefoto\Support\Application\Question\Contract\GuestAddressHasherInterface;
use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\QuestionKeySealInterface;
use Morefoto\Support\Application\Question\Contract\StaffQuestionContextInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Message\Handler\DeliverQuestionMessageHandler;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\ParentQuestionAccess;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Application\Question\UseCase\AddGalleryQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\AddStaffQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\AskGalleryQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\ConsumeQuestionMessagesUseCase;
use Morefoto\Support\Application\Question\UseCase\DeliverQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\DispatchPendingQuestionMessagesUseCase;
use Morefoto\Support\Application\Question\UseCase\GetGalleryQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\GetStaffQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\SendGuestFeedbackUseCase;
use Morefoto\Support\Domain\Question\Repository\MaxChatRepositoryInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Infrastructure\Context\GalleryQuestionContext;
use Morefoto\Support\Infrastructure\Context\StaffQuestionContext;
use Morefoto\Support\Infrastructure\Crypto\GuestAddressHasher;
use Morefoto\Support\Infrastructure\Crypto\QuestionKeySeal;
use Morefoto\Support\Infrastructure\Database\BitrixMaxChatRepository;
use Morefoto\Support\Infrastructure\Database\BitrixQuestionDeliveryRepository;
use Morefoto\Support\Infrastructure\Database\BitrixQuestionRepository;
use Morefoto\Support\Infrastructure\Database\BitrixSupportTransaction;
use Morefoto\Support\Infrastructure\Database\SupportSql;
use Morefoto\Support\Infrastructure\Organization\StructureSupportRemoval;
use Morefoto\Support\Infrastructure\Messenger\QuestionDeliveryPublisher;
use Morefoto\Support\Infrastructure\Messenger\SupportMessengerFactory;
use Morefoto\Support\Presentation\Command\ConsumeQuestionMessagesCommand;
use Morefoto\Support\Presentation\Command\DispatchPendingQuestionMessagesCommand;
use Morefoto\Support\Presentation\Command\MaxStatusCommand;
use Morefoto\Support\Presentation\Command\MaxSubscribeCommand;
use Morefoto\Support\Presentation\Controller\GalleryQuestionController;
use Morefoto\Support\Presentation\Controller\GuestFeedbackController;
use Morefoto\Support\Presentation\Controller\MaxWebhookController;
use Morefoto\Support\Presentation\Controller\StaffQuestionController;
use Morefoto\Support\Presentation\Feedback\FeedbackMapper;
use Morefoto\Support\Presentation\Max\MaxUpdateMapper;
use Morefoto\Support\Presentation\Question\QuestionInputMapper;
use Morefoto\Support\Presentation\Question\QuestionResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Support\StructureSupportRemovalInterface;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Application\Contract\Notification\MaxBotAdminInterface;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Transport\TransportInterface;

$get = static fn(string $id): object => ServiceLocator::getInstance()->get($id);
// 0 — the curator group is not configured yet: questions are stored and stay pending, webhook replies are ignored.
$chatId = static fn(): int => (int)(getenv('MOREFOTO_SUPPORT_MAX_CHAT_ID') ?: 0);

$services = [
    SupportSql::class => ['className' => SupportSql::class],
    SupportTransactionInterface::class => ['constructor' => static fn(): SupportTransactionInterface => new BitrixSupportTransaction()],
    StructureSupportRemovalInterface::class => ['constructor' => static fn(): StructureSupportRemovalInterface => new StructureSupportRemoval()],
    QuestionRepositoryInterface::class => [
        'constructor' => static fn(): QuestionRepositoryInterface => new BitrixQuestionRepository($get(SupportSql::class)),
    ],
    QuestionDeliveryRepositoryInterface::class => [
        'constructor' => static fn(): QuestionDeliveryRepositoryInterface => new BitrixQuestionDeliveryRepository($get(SupportSql::class)),
    ],
    MaxChatRepositoryInterface::class => [
        'constructor' => static fn(): MaxChatRepositoryInterface => new BitrixMaxChatRepository($get(SupportSql::class)),
    ],
    QuestionKeySealInterface::class => ['constructor' => static fn(): QuestionKeySealInterface => new QuestionKeySeal()],
    // The server secret of the platform; without it guest feedback answers 503 instead of storing a guessable IP hash.
    GuestAddressHasherInterface::class => [
        'constructor' => static fn(): GuestAddressHasherInterface => new GuestAddressHasher((string)(getenv('REBIT_ENCRYPTION_KEY') ?: '')),
    ],
    GalleryQuestionContextInterface::class => [
        'constructor' => static fn(): GalleryQuestionContextInterface => new GalleryQuestionContext(
            $get(GalleryAccessInterface::class),
            $get(GroupDirectoryInterface::class),
            $get(InstitutionAccessInterface::class),
        ),
    ],
    StaffQuestionContextInterface::class => [
        'constructor' => static fn(): StaffQuestionContextInterface => new StaffQuestionContext(
            $get(StaffRequestAccessInterface::class),
            $get(GroupDirectoryInterface::class),
        ),
    ],
    MessengerQueueEnum::SUPPORT_MAX->transportKey() => [
        'constructor' => static fn(): TransportInterface => $get(AmqpConnectionFactory::class)->create(MessengerQueueEnum::SUPPORT_MAX),
    ],
    QuestionDeliveryPublisherInterface::class => [
        'constructor' => static fn(): QuestionDeliveryPublisherInterface => new QuestionDeliveryPublisher(
            SupportMessengerFactory::createPublisher(ServiceLocator::getInstance()),
            Log::channel(LogChannelEnum::support),
        ),
    ],
    QuestionTextPolicy::class => ['className' => QuestionTextPolicy::class],
    MaxQuestionTextBuilder::class => ['className' => MaxQuestionTextBuilder::class],
    QuestionInputMapper::class => ['className' => QuestionInputMapper::class],
    QuestionResultMapper::class => ['className' => QuestionResultMapper::class],
    MaxUpdateMapper::class => ['className' => MaxUpdateMapper::class],
    FeedbackMapper::class => ['className' => FeedbackMapper::class],
    DeliverQuestionMessageUseCase::class => [
        'className' => DeliverQuestionMessageUseCase::class,
        'constructorParams' => static fn(): array => [
            $get(SupportTransactionInterface::class),
            $get(QuestionDeliveryRepositoryInterface::class),
            $get(MaxChatMessengerInterface::class),
            $get(MaxQuestionTextBuilder::class),
            $get(QuestionDeliveryPublisherInterface::class),
            $get(ClockInterface::class),
            $chatId(),
        ],
    ],
    HandleMaxUpdateUseCase::class => [
        'className' => HandleMaxUpdateUseCase::class,
        'constructorParams' => static fn(): array => [
            $get(SupportTransactionInterface::class),
            $get(QuestionRepositoryInterface::class),
            $get(MaxChatRepositoryInterface::class),
            $get(QuestionTextPolicy::class),
            $get(ClockInterface::class),
            $chatId(),
        ],
    ],
    SubscribeMaxWebhookUseCase::class => [
        'className' => SubscribeMaxWebhookUseCase::class,
        'constructorParams' => static fn(): array => [
            $get(MaxBotAdminInterface::class),
            (string)(getenv('MOREFOTO_SUPPORT_MAX_WEBHOOK_SECRET') ?: ''),
        ],
    ],
    GetMaxStatusUseCase::class => [
        'className' => GetMaxStatusUseCase::class,
        'constructorParams' => static fn(): array => [
            $get(MaxBotAdminInterface::class),
            $get(MaxChatRepositoryInterface::class),
            $get(QuestionDeliveryRepositoryInterface::class),
            $chatId(),
        ],
    ],
    ConsumeQuestionMessagesUseCase::class => [
        'className' => ConsumeQuestionMessagesUseCase::class,
        'constructorParams' => static fn(): array => [
            $get(MessageConsumerRunnerInterface::class),
            $get(MessageTransportFactoryInterface::class),
            SupportMessengerFactory::createBus(ServiceLocator::getInstance()),
        ],
    ],
];
$dependencies = [
    QuestionHistory::class => [QuestionRepositoryInterface::class],
    QuestionMessageRecorder::class => [QuestionRepositoryInterface::class],
    ParentQuestionAccess::class => [QuestionRepositoryInterface::class],
    AskGalleryQuestionUseCase::class => [GalleryQuestionContextInterface::class, SupportTransactionInterface::class, QuestionRepositoryInterface::class, QuestionMessageRecorder::class,
        QuestionKeySealInterface::class, QuestionTextPolicy::class, MaxQuestionTextBuilder::class, QuestionHistory::class, QuestionDeliveryPublisherInterface::class, ClockInterface::class],
    GetGalleryQuestionUseCase::class => [ParentQuestionAccess::class, QuestionHistory::class],
    AddGalleryQuestionMessageUseCase::class => [ParentQuestionAccess::class, SupportTransactionInterface::class, QuestionRepositoryInterface::class, QuestionMessageRecorder::class,
        QuestionTextPolicy::class, QuestionHistory::class, QuestionDeliveryPublisherInterface::class, ClockInterface::class],
    GetStaffQuestionUseCase::class => [StaffQuestionContextInterface::class, QuestionRepositoryInterface::class, QuestionHistory::class],
    AddStaffQuestionMessageUseCase::class => [StaffQuestionContextInterface::class, SupportTransactionInterface::class, QuestionRepositoryInterface::class, QuestionMessageRecorder::class,
        QuestionTextPolicy::class, MaxQuestionTextBuilder::class, QuestionHistory::class, QuestionDeliveryPublisherInterface::class, ClockInterface::class],
    SendGuestFeedbackUseCase::class => [SupportTransactionInterface::class, QuestionRepositoryInterface::class, QuestionMessageRecorder::class, QuestionTextPolicy::class,
        MaxQuestionTextBuilder::class, QuestionDeliveryPublisherInterface::class, GuestAddressHasherInterface::class, ClockInterface::class],
    DispatchPendingQuestionMessagesUseCase::class => [SupportTransactionInterface::class, QuestionDeliveryRepositoryInterface::class, QuestionDeliveryPublisherInterface::class, ClockInterface::class],
    DeliverQuestionMessageHandler::class => [DeliverQuestionMessageUseCase::class],
    GalleryQuestionController::class => [AskGalleryQuestionUseCase::class, GetGalleryQuestionUseCase::class, AddGalleryQuestionMessageUseCase::class, QuestionInputMapper::class, QuestionResultMapper::class],
    StaffQuestionController::class => [GetStaffQuestionUseCase::class, AddStaffQuestionMessageUseCase::class, QuestionInputMapper::class, QuestionResultMapper::class],
    GuestFeedbackController::class => [SendGuestFeedbackUseCase::class, FeedbackMapper::class],
    MaxWebhookController::class => [HandleMaxUpdateUseCase::class, MaxUpdateMapper::class],
    ConsumeQuestionMessagesCommand::class => [ConsumeQuestionMessagesUseCase::class],
    DispatchPendingQuestionMessagesCommand::class => [DispatchPendingQuestionMessagesUseCase::class],
    MaxStatusCommand::class => [GetMaxStatusUseCase::class],
    MaxSubscribeCommand::class => [SubscribeMaxWebhookUseCase::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
