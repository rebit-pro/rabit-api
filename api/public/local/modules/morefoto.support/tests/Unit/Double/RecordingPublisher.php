<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit\Double;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;

final class RecordingPublisher implements QuestionDeliveryPublisherInterface
{
    /** @var list<int> */
    public array $published = [];

    public function publish(int $messageId): void
    {
        $this->published[] = $messageId;
    }
}
