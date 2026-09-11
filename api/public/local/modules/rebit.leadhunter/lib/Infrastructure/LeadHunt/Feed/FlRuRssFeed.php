<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Feed;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\LeadFeedInterface;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\ValueObject\HuntRule;

/**
 * RSS-лента заказов fl.ru (последние 60 заказов).
 *
 * Раздел задаётся feedParams правила: category / subcategory
 * (например «Сайты под ключ» = category=2, subcategory=27); без параметров — общая лента.
 * Публичного API у fl.ru нет, RSS — единственный стабильный канал.
 *
 * cURL с браузерным User-Agent: fl.ru закрывает «голых» ботов антибот-фильтром.
 */
final readonly class FlRuRssFeed implements LeadFeedInterface
{
    private const int TIMEOUT_SECONDS = 30;

    private const string USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public function __construct(
        private LoggerInterface $logger,
        private string $feedUrl,
    ) {}

    public function fetch(HuntRule $rule): array
    {
        $xml = $this->download($this->buildUrl($rule));

        if (null === $xml) {
            return [];
        }

        return $this->parse($xml);
    }

    /**
     * Разбор RSS. Публичный для тестов на xml-фикстуре.
     *
     * @return list<ExternalLeadDto>
     */
    public function parse(string $xml): array
    {
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        if (false === $document || !isset($document->channel->item)) {
            $this->logger->error('Не удалось разобрать RSS fl.ru', ['prefix' => mb_substr($xml, 0, 200)]);

            return [];
        }

        $leads = [];

        foreach ($document->channel->item as $item) {
            $guid = trim((string)$item->guid);
            $url = trim((string)$item->link);

            if ('' === $guid) {
                $guid = $url;
            }

            if ('' === $guid) {
                continue;
            }

            $leads[] = new ExternalLeadDto(
                source: LeadSourceEnum::FL_RU,
                guid: $guid,
                title: $this->text($item->title),
                description: $this->text($item->description),
                url: $url,
                publishedAt: $this->parsePubDate((string)$item->pubDate),
            );
        }

        return $leads;
    }

    /**
     * Текст fl.ru приходит в CDATA с HTML-энтити внутри (&#8381; и т.п.) —
     * XML-парсер их не трогает, декодируем сами до чистого UTF-8.
     */
    private function text(?\SimpleXMLElement $node): string
    {
        return trim(html_entity_decode((string)$node, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function buildUrl(HuntRule $rule): string
    {
        if ([] === $rule->feedParams) {
            return $this->feedUrl;
        }

        return $this->feedUrl . '?' . http_build_query($rule->feedParams);
    }

    private function download(string $url): ?string
    {
        $handle = curl_init($url);

        if (false === $handle) {
            $this->logger->error('Не удалось инициализировать запрос к fl.ru', ['url' => $url]);

            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);

        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || 200 !== $status) {
            $this->logger->error('Лента fl.ru недоступна', [
                'url' => $url,
                'status' => $status,
                'error' => $error,
            ]);

            return null;
        }

        return $response;
    }

    private function parsePubDate(string $pubDate): ?\DateTimeImmutable
    {
        $pubDate = trim($pubDate);

        if ('' === $pubDate) {
            return null;
        }

        try {
            return new \DateTimeImmutable($pubDate);
        } catch (\Exception) {
            return null;
        }
    }
}
