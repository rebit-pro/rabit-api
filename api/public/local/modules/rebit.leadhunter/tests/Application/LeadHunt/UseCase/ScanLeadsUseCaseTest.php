<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Tests\Application\LeadHunt\UseCase;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\ExternalLeadRepositoryInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntRuleProviderInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\LeadFeedInterface;
use Rebit\Leadhunter\Application\LeadHunt\Service\LeadFeedRegistry;
use Rebit\Leadhunter\Application\LeadHunt\UseCase\ScanLeadsUseCase;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\Service\KeywordMatcher;
use Rebit\Leadhunter\Domain\LeadHunt\ValueObject\HuntRule;

/**
 * @internal
 */
final class ScanLeadsUseCaseTest extends TestCase
{
    public function testStoresOnlyLeadsMatchedByKeywords(): void
    {
        $feed = $this->createStub(LeadFeedInterface::class);
        $feed->method('fetch')->willReturn([
            $this->lead('guid-1', 'Доработать сайт на Битрикс'),
            $this->lead('guid-2', 'Лендинг на Tilda'),
        ]);

        $repository = $this->createMock(ExternalLeadRepositoryInterface::class);
        $repository->method('findExistingGuids')->willReturn([]);
        $repository->method('findPending')->willReturn([]);
        $repository->expects(self::once())
            ->method('add')
            ->with(
                self::callback(static fn(ExternalLeadDto $lead): bool => 'guid-1' === $lead->guid),
                ['битрикс'],
            )
        ;

        $result = $this->useCase([new HuntRule(LeadSourceEnum::FL_RU, [], ['битрикс'])], $feed, $repository)
            ->execute()
        ;

        self::assertSame(1, $result->matched);
        self::assertSame(1, $result->added);
    }

    public function testSectionRuleStoresEverything(): void
    {
        $feed = $this->createStub(LeadFeedInterface::class);
        $feed->method('fetch')->willReturn([
            $this->lead('guid-1', 'Сайт под ключ для стоматологии'),
            $this->lead('guid-2', 'Лендинг на Tilda'),
        ]);

        $repository = $this->createMock(ExternalLeadRepositoryInterface::class);
        $repository->method('findExistingGuids')->willReturn([]);
        $repository->method('findPending')->willReturn([]);
        $repository->expects(self::exactly(2))->method('add');

        $result = $this->useCase([new HuntRule(LeadSourceEnum::FL_RU, ['category' => 2])], $feed, $repository)
            ->execute()
        ;

        self::assertSame(2, $result->matched);
        self::assertSame(2, $result->added);
    }

    public function testSkipsAlreadyKnownLeads(): void
    {
        $feed = $this->createStub(LeadFeedInterface::class);
        $feed->method('fetch')->willReturn([
            $this->lead('guid-known', 'Сайт на битрикс'),
        ]);

        $repository = $this->createMock(ExternalLeadRepositoryInterface::class);
        $repository->method('findExistingGuids')->willReturn(['guid-known']);
        $repository->method('findPending')->willReturn([]);
        $repository->expects(self::never())->method('add');

        $result = $this->useCase([new HuntRule(LeadSourceEnum::FL_RU, [], ['битрикс'])], $feed, $repository)
            ->execute()
        ;

        self::assertSame(1, $result->matched);
        self::assertSame(0, $result->added);
    }

    public function testMergesKeywordsWhenLeadMatchesSeveralRules(): void
    {
        $feed = $this->createStub(LeadFeedInterface::class);
        $feed->method('fetch')->willReturn([
            $this->lead('guid-1', 'Перенос магазина с Битрикс (bitrix)'),
        ]);

        $repository = $this->createMock(ExternalLeadRepositoryInterface::class);
        $repository->method('findExistingGuids')->willReturn([]);
        $repository->method('findPending')->willReturn([]);
        $repository->expects(self::once())
            ->method('add')
            ->with(self::anything(), ['битрикс', 'bitrix'])
        ;

        $result = $this->useCase(
            [
                new HuntRule(LeadSourceEnum::FL_RU, [], ['битрикс']),
                new HuntRule(LeadSourceEnum::FL_RU, [], ['bitrix']),
            ],
            $feed,
            $repository,
        )->execute();

        self::assertSame(1, $result->matched);
        self::assertSame(1, $result->added);
    }

    public function testMarksLeadFailedWhenDeliveryFails(): void
    {
        $feed = $this->createStub(LeadFeedInterface::class);
        $feed->method('fetch')->willReturn([]);

        $pending = new PendingLeadDto(
            id: 7,
            source: LeadSourceEnum::FL_RU,
            title: 'Сайт на битрикс',
            description: '',
            url: 'https://www.fl.ru/projects/1/x.html',
            matchedKeywords: ['битрикс'],
            attempts: 1,
        );

        $repository = $this->createMock(ExternalLeadRepositoryInterface::class);
        $repository->method('findExistingGuids')->willReturn([]);
        $repository->method('findPending')->willReturn([$pending]);
        $repository->expects(self::never())->method('markSent');
        $repository->expects(self::once())->method('markFailed')->with(7, 2);

        $result = $this->useCase([new HuntRule(LeadSourceEnum::FL_RU, [], ['битрикс'])], $feed, $repository, notifyResult: false)
            ->execute()
        ;

        self::assertSame(0, $result->sent);
        self::assertSame(1, $result->failed);
    }

    /**
     * @param list<HuntRule> $rules
     */
    private function useCase(
        array $rules,
        LeadFeedInterface $feed,
        ExternalLeadRepositoryInterface $repository,
        bool $notifyResult = true,
    ): ScanLeadsUseCase {
        $ruleProvider = $this->createStub(HuntRuleProviderInterface::class);
        $ruleProvider->method('getRules')->willReturn($rules);

        $notifier = $this->createStub(HuntNotifierInterface::class);
        $notifier->method('notify')->willReturn($notifyResult);

        return new ScanLeadsUseCase(
            $ruleProvider,
            new LeadFeedRegistry([LeadSourceEnum::FL_RU->value => $feed]),
            new KeywordMatcher(),
            $repository,
            $notifier,
            new NullLogger(),
        );
    }

    private function lead(string $guid, string $title): ExternalLeadDto
    {
        return new ExternalLeadDto(
            source: LeadSourceEnum::FL_RU,
            guid: $guid,
            title: $title,
            description: 'Описание заказа',
            url: 'https://www.fl.ru/projects/1/x.html',
            publishedAt: null,
        );
    }
}
