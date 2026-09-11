<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Application\Lead\UseCase;

use PHPUnit\Framework\TestCase;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Notification\Application\Lead\UseCase\SubmitLeadUseCase;

/**
 * @internal
 */
final class SubmitLeadUseCaseTest extends TestCase
{
    public function testDeliversSubmittedLeadWithAttachment(): void
    {
        $attachment = new LeadAttachmentDto(
            path: __FILE__,
            name: 'brief.txt',
            mimeType: 'text/plain',
            size: 12,
        );
        $notifier = $this->createMock(LeadNotifierInterface::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                self::callback(static function(LeadMessageDto $lead): bool {
                    self::assertSame('Иван', $lead->name);
                    self::assertSame('+7 900 000-00-00', $lead->phone);
                    self::assertSame('Нужен сайт с каталогом', $lead->description);
                    self::assertSame('https://example.com/estimate', $lead->page);
                    self::assertSame('ivan@example.com', $lead->email);

                    return true;
                }),
                self::identicalTo($attachment),
            )
        ;

        $result = new SubmitLeadUseCase($notifier)->execute(new SubmitLeadRequestDto(
            name: ' Иван ',
            phone: ' +7 900 000-00-00 ',
            description: ' Нужен сайт с каталогом ',
            email: ' ivan@example.com ',
            page: ' https://example.com/estimate ',
        ), $attachment);

        self::assertTrue($result->accepted);
    }

    public function testHoneypotAcceptsWithoutSending(): void
    {
        $notifier = $this->createMock(LeadNotifierInterface::class);
        $notifier->expects(self::never())->method('notify');

        $result = new SubmitLeadUseCase($notifier)->execute(new SubmitLeadRequestDto(
            name: 'Бот',
            phone: '+7 900 000-00-00',
            description: 'Автоматическая заявка',
            company: 'Bot Company',
        ));

        self::assertTrue($result->accepted);
    }
}
