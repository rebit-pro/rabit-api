<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Feedback;

use Morefoto\Support\Application\Question\Dto\SendGuestFeedbackInputDto;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Morefoto\Support\Presentation\Feedback\Request\Dto\SendFeedbackRequestDto;
use Morefoto\Support\Presentation\Feedback\Result\Dto\FeedbackAcceptedResultDto;

/** Converts the login page feedback request into Application input and the accepted number into the API result. */
final readonly class FeedbackMapper
{
    public function input(SendFeedbackRequestDto $request): SendGuestFeedbackInputDto
    {
        return new SendGuestFeedbackInputDto($request->name, $request->contact, $request->message, $request->clientAddress);
    }

    public function key(SendFeedbackRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }

    public function accepted(int $number): FeedbackAcceptedResultDto
    {
        return new FeedbackAcceptedResultDto($number);
    }
}
