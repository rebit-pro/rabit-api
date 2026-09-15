<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Application\Lead\UseCase;

use PHPUnit\Framework\TestCase;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Application\Lead\Port\MosDizelLeadNotifierInterface;
use Rebit\Notification\Application\Lead\UseCase\SubmitMosDizelLeadUseCase;

/**
 * @internal
 */
final class SubmitMosDizelLeadUseCaseTest extends TestCase
{
    public function testUsesDedicatedNotifier(): void
    {
        $notifier = $this->createMock(MosDizelLeadNotifierInterface::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(self::callback(static function(LeadMessageDto $lead): bool {
                self::assertSame('Тест', $lead->name);
                self::assertSame('+7 999 000-00-00', $lead->phone);

                return true;
            }))
        ;

        $result = new SubmitMosDizelLeadUseCase($notifier)->execute(new SubmitLeadRequestDto(
            name: ' Тест ',
            phone: ' +7 999 000-00-00 ',
            description: ' Проверка email ',
        ));

        self::assertTrue($result->accepted);
    }

    public function testHoneypotDoesNotNotify(): void
    {
        $notifier = $this->createMock(MosDizelLeadNotifierInterface::class);
        $notifier->expects(self::never())->method('notify');

        $result = new SubmitMosDizelLeadUseCase($notifier)->execute(new SubmitLeadRequestDto(
            name: 'Бот',
            phone: '+7 999 000-00-00',
            description: 'Проверка',
            company: 'Bot',
        ));

        self::assertTrue($result->accepted);
    }
}
