<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Message\Handler;

use Morefoto\Support\Application\Question\Message\DeliverQuestionMessage;
use Morefoto\Support\Application\Question\UseCase\DeliverQuestionMessageUseCase;

final readonly class DeliverQuestionMessageHandler
{
    public function __construct(private DeliverQuestionMessageUseCase $deliver) {}

    public function __invoke(DeliverQuestionMessage $message): void
    {
        $this->deliver->execute($message->messageId);
    }
}
