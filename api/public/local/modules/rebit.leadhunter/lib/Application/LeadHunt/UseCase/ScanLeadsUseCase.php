<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\UseCase;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Dto\ScanLeadsOutputDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\ExternalLeadRepositoryInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntRuleProviderInterface;
use Rebit\Leadhunter\Application\LeadHunt\Service\LeadFeedRegistry;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\Service\KeywordMatcher;

/**
 * Один прогон охоты: читаем ленты по правилам, отбираем совпадения,
 * сохраняем новые заявки и доставляем накопившиеся в Telegram.
 *
 * Дедупликация:
 *  - внутри прогона — заявка, попавшая под несколько правил, учитывается один раз
 *    (сработавшие ключевые слова объединяются);
 *  - между прогонами — по уникальному (source, guid) в хранилище.
 *
 * Доставка отвязана от находки: заявки шлются из хранилища пачкой с паузой
 * (лимит Telegram ~20 сообщений/мин в один чат), недоставленные ретраятся
 * следующими прогонами до MAX_SEND_ATTEMPTS.
 */
final readonly class ScanLeadsUseCase
{
    public const int MAX_SEND_ATTEMPTS = 5;

    private const int SEND_LIMIT_PER_RUN = 20;

    private const int SEND_DELAY_SECONDS = 3;

    private const int RETENTION_DAYS = 30;

    public function __construct(
        private HuntRuleProviderInterface $ruleProvider,
        private LeadFeedRegistry $feedRegistry,
        private KeywordMatcher $keywordMatcher,
        private ExternalLeadRepositoryInterface $repository,
        private HuntNotifierInterface $notifier,
        private LoggerInterface $logger,
    ) {}

    public function execute(): ScanLeadsOutputDto
    {
        $matchedLeads = $this->collectMatchedLeads();
        $added = $this->storeNewLeads($matchedLeads);
        [$sent, $failed] = $this->deliverPending();

        $this->repository->deleteOlderThanDays(self::RETENTION_DAYS);

        return new ScanLeadsOutputDto(
            matched: count($matchedLeads),
            added: $added,
            sent: $sent,
            failed: $failed,
        );
    }

    /**
     * @return array<string, array{lead: ExternalLeadDto, keywords: list<string>}> ключ — "source:guid"
     */
    private function collectMatchedLeads(): array
    {
        $rules = $this->ruleProvider->getRules();

        if ([] === $rules) {
            $this->logger->warning('Правила охоты не настроены: REBIT_LEADHUNTER_RULES пуст или невалиден');

            return [];
        }

        /** @var array<string, array{lead: ExternalLeadDto, keywords: list<string>}> $matched */
        $matched = [];

        foreach ($rules as $rule) {
            $feed = $this->feedRegistry->get($rule->source);

            if (null === $feed) {
                $this->logger->error('Для площадки не зарегистрирована лента', ['source' => $rule->source->value]);

                continue;
            }

            foreach ($feed->fetch($rule) as $lead) {
                $keywords = $rule->matchesEverything()
                    ? []
                    : $this->keywordMatcher->match($rule->keywords, $lead->title . ' ' . $lead->description);

                if (!$rule->matchesEverything() && [] === $keywords) {
                    continue;
                }

                $key = $lead->source->value . ':' . $lead->guid;

                $matched[$key] = [
                    'lead' => $lead,
                    'keywords' => isset($matched[$key])
                        ? array_values(array_unique([...$matched[$key]['keywords'], ...$keywords]))
                        : $keywords,
                ];
            }
        }

        return $matched;
    }

    /**
     * @param array<string, array{lead: ExternalLeadDto, keywords: list<string>}> $matchedLeads
     *
     * @return int сколько новых заявок сохранено
     */
    private function storeNewLeads(array $matchedLeads): int
    {
        if ([] === $matchedLeads) {
            return 0;
        }

        /** @var array<string, list<string>> $guidsBySource */
        $guidsBySource = [];

        foreach ($matchedLeads as ['lead' => $lead]) {
            $guidsBySource[$lead->source->value][] = $lead->guid;
        }

        /** @var array<string, true> $existing */
        $existing = [];

        foreach ($guidsBySource as $sourceValue => $guids) {
            $source = LeadSourceEnum::from($sourceValue);

            foreach ($this->repository->findExistingGuids($source, $guids) as $guid) {
                $existing[$sourceValue . ':' . $guid] = true;
            }
        }

        $added = 0;

        foreach ($matchedLeads as $key => ['lead' => $lead, 'keywords' => $keywords]) {
            if (isset($existing[$key])) {
                continue;
            }

            $this->repository->add($lead, $keywords);
            ++$added;
        }

        if (0 < $added) {
            $this->logger->info('Найдены новые внешние заявки', ['added' => $added]);
        }

        return $added;
    }

    /**
     * @return array{int, int} [отправлено, провалено]
     */
    private function deliverPending(): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($this->repository->findPending(self::SEND_LIMIT_PER_RUN, self::MAX_SEND_ATTEMPTS) as $index => $lead) {
            if (0 < $index) {
                sleep(self::SEND_DELAY_SECONDS);
            }

            if ($this->notifier->notify($lead)) {
                $this->repository->markSent($lead->id);
                ++$sent;

                continue;
            }

            $this->repository->markFailed($lead->id, $lead->attempts + 1);
            ++$failed;
        }

        return [$sent, $failed];
    }
}
