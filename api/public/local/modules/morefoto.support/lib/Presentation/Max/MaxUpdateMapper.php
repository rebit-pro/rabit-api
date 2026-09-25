<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max;

use Morefoto\Support\Application\Max\Dto\MaxUpdateInputDto;
use Morefoto\Support\Presentation\Max\Request\Dto\MaxUpdateRequestDto;
use Morefoto\Support\Presentation\Max\Result\Dto\MaxAcknowledgedResultDto;

/** Flattens a MAX Update into Application input: chat, reply link, text and sender name. */
final readonly class MaxUpdateMapper
{
    public function update(MaxUpdateRequestDto $request): MaxUpdateInputDto
    {
        $message = $request->message;
        $sender = $message?->sender;
        $name = null === $sender ? '' : trim($sender->firstName . ' ' . ($sender->lastName ?? ''));

        return new MaxUpdateInputDto(
            updateType: $request->updateType,
            chatId: $message?->recipient->chatId ?? $request->chatId,
            chatType: $message?->recipient->chatType,
            mid: $message?->body->mid,
            replyToMid: 'reply' === $message?->link?->type ? $message->link->message->mid : null,
            text: $message?->body->text,
            senderName: '' === $name ? 'Куратор' : mb_substr($name, 0, 120),
            senderIsBot: true === $sender?->isBot,
        );
    }

    public function acknowledged(): MaxAcknowledgedResultDto
    {
        return new MaxAcknowledgedResultDto(true);
    }
}
