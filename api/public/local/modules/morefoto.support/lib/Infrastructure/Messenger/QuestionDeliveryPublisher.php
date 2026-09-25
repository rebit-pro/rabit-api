<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Messenger;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Message\DeliverQuestionMessage;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

/** Сбой RabbitMQ не отменяет сохранённый вопрос: реплика остаётся pending до dispatcher. */
final readonly class QuestionDeliveryPublisher implements QuestionDeliveryPublisherInterface
{
    public function __construct(
        private MessagePublisherInterface $publisher,
        private LoggerInterface $logger,
    ) {}

    public function publish(int $messageId): void
    {
        try {
            $this->publisher->dispatch(new DeliverQuestionMessage($messageId), 15);
        } catch (\Throwable $error) {
            $this->logger->warning('Support message remains pending after publish failure.', ['exception' => $error::class]);
        }
    }
}
