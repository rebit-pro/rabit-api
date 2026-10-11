<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerInputDto;
use Morefoto\Commerce\Application\Order\UseCase\CreateOrderUseCase;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey as OrderKey;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionFinishInputDto;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionStartInputDto;
use Morefoto\Handoff\Application\Link\UseCase\FinishMarkupCorrectionUseCase;
use Morefoto\Handoff\Application\Link\UseCase\GetGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\StartMarkupCorrectionUseCase;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey as LinkKey;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\UseCase\AssignPhotosUseCase;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey as MediaKey;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Sprint\Migration\Version20261011180001;

if ('test' !== getenv('APP_ENV') || !is_file('/runtime/e4-fixture.json')) {
    throw new RuntimeException('Markup correction verification requires the disposable fixture.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff', 'morefoto.commerce', 'morefoto.legal', 'sprint.migration'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing module ' . $module);
    }
}
$services = ServiceLocator::getInstance();
$connection = Application::getConnection();
$fixture = json_decode((string)file_get_contents('/runtime/e4-fixture.json'), true, flags: JSON_THROW_ON_ERROR)['markup-db'];
$groupId = (string)$fixture['groupId'];
$token = (string)$fixture['token'];
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('D4 check failed: ' . $message);
    }
};
$expect = static function(string $code, callable $operation): void {
    try {
        $operation();
    } catch (HttpException $error) {
        if ($code === $error->getMessage()) {
            return;
        }
        throw new RuntimeException('Expected ' . $code . ', got ' . $error->getMessage());
    }
    throw new RuntimeException('Expected rejection: ' . $code);
};
$rejected = static function(string $sql) use ($connection): bool {
    try {
        $connection->queryExecute($sql);
    } catch (Throwable) {
        return true;
    }

    return false;
};
$row = static fn(): array => $connection->query("SELECT ID,UF_REVISION,DATE_FORMAT(UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS CLOSES_AT,UF_MARKUP_CORRECTION_AT FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch();
$organizer = (int)$connection->query("SELECT ID FROM b_user WHERE LOGIN='organizer@example.invalid'")->fetch()['ID'];
$linkKey = static fn(): LinkKey => new LinkKey(bin2hex(random_bytes(16)));
$read = $services->get(GetGroupLinkUseCase::class);
$start = $services->get(StartMarkupCorrectionUseCase::class);
$finish = $services->get(FinishMarkupCorrectionUseCase::class);
$quotes = $services->get(CreateQuoteUseCase::class);
$orders = $services->get(CreateOrderUseCase::class);
$gallery = $services->get(GalleryAccessInterface::class);
$proof = [];

// 1. Schema: only a transmitted group is corrected, and the link history accepts only well-formed D4 events.
$preparing = $connection->query('SELECT ID FROM b_hlbd_mf_group WHERE UF_SENT_AT IS NULL LIMIT 1')->fetch();
$check(false !== $preparing, 'an untransmitted group exists in the fixture');
$check($rejected('UPDATE b_hlbd_mf_group SET UF_MARKUP_CORRECTION_AT=UTC_TIMESTAMP() WHERE ID=' . (int)$preparing['ID']), 'CHECK refuses a correction before transmission');
$before = $row();
$check($rejected("INSERT INTO mf_group_link_history(GROUP_ID,KIND,ACTOR_ID,ACTOR_NAME,REASON,CREATED_AT) VALUES({$before['ID']},'markup_started',{$organizer},'x','reason',UTC_TIMESTAMP())"), 'CHECK refuses a started event with a reason');
$check($rejected("INSERT INTO mf_group_link_history(GROUP_ID,KIND,ACTOR_ID,ACTOR_NAME,CREATED_AT) VALUES({$before['ID']},'markup_finished',{$organizer},'x',UTC_TIMESTAMP())"), 'CHECK refuses a finished event without the signature');
$proof[] = 'schema checks';

// 2. A quote made before the correction; the correction will make it stale.
$product = $connection->query("SELECT UF_UUID FROM b_hlbd_mf_product WHERE UF_KIND='bundle' AND UF_ACTIVE=1 ORDER BY ID LIMIT 1")->fetch();
$check(false !== $product, 'an active bundle exists after the storefront specs');
$assignment = $gallery->resolve($token)->assignments[0];
$oldLines = [new QuoteLineInputDto($assignment->assignmentId, (string)$product['UF_UUID'], 1)];
$oldQuote = $quotes->execute($token, $oldLines);
$legal = $services->get(LegalDocumentCatalogInterface::class);
$consents = [];
foreach ([LegalDocumentEnum::BUYER_CONSENT, LegalDocumentEnum::OFFER] as $document) {
    $consents[] = new AcceptedDocumentDto($document->value, $legal->current($document)->version);
}
$buyer = new OrderBuyerInputDto('Проверка исправления', '+79005551616', 'markup.d4@example.test', '', null, true);

// 3. Serialization with order creation, proved with a second MySQL connection holding the group row.
[$host, $port] = array_pad(explode(':', (string)$connection->getHost(), 2), 2, '3306');
$other = new mysqli($host, (string)$connection->getLogin(), (string)$connection->getPassword(), (string)$connection->getDatabase(), (int)$port);
$other->set_charset('utf8mb4');
$connection->queryExecute('SET SESSION innodb_lock_wait_timeout=1');
// 3a. An order transaction has read the group (SERIALIZABLE shared lock): the start waits and gives up without changes.
$other->query('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
$other->query('START TRANSACTION');
$other->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch_all();
$revision = $read->execute($organizer, $groupId)->revision;
$expect('HANDOFF_UNAVAILABLE', static fn(): object => $start->execute($organizer, $groupId, $linkKey(), new MarkupCorrectionStartInputDto($revision)));
$other->query('ROLLBACK');
$check(null === $row()['UF_MARKUP_CORRECTION_AT'], 'a start waiting on an order changed nothing');
// 3b. A started correction holds the group exclusively: a quote of the buyer waits for it.
$other->query('START TRANSACTION');
$other->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}' FOR UPDATE")->fetch_all();
try {
    $quotes->execute($token, $oldLines);
    $check(false, 'a quote passed the exclusive group lock');
} catch (Throwable $error) {
    $messages = '';
    for ($current = $error; null !== $current; $current = $current->getPrevious()) {
        $messages .= $current->getMessage();
    }
    $check(str_contains($messages, 'Lock wait timeout exceeded'), 'the quote waited on the group lock, got ' . $error->getMessage());
}
$other->query('ROLLBACK');
$other->close();
$connection->queryExecute('SET SESSION innodb_lock_wait_timeout=50');
$proof[] = 'start and quote serialize on the group row';

// 4. Start: sales are paused, T0 and the deadlines stay, the organization and link journals record it.
$card = $read->execute($organizer, $groupId);
$check($card->markupCorrectable && null === $card->markupCorrectionAt, 'a transmitted group without orders may be corrected');
$started = $start->execute($organizer, $groupId, $linkKey(), new MarkupCorrectionStartInputDto($card->revision));
$paused = $row();
$check(null !== $started->startedAt && null !== $paused['UF_MARKUP_CORRECTION_AT'], 'the correction moment is stored');
$check((int)$paused['UF_REVISION'] === (int)$before['UF_REVISION'] + 1, 'the group revision grows once');
$check($paused['SENT_AT'] === $before['SENT_AT'] && $paused['CLOSES_AT'] === $before['CLOSES_AT'], 'T0 and the print deadline stay');
$expect('GALLERY_UPDATING', static fn(): object => $quotes->execute($token, $oldLines));
$expect('GALLERY_UPDATING', static fn(): object => $orders->execute($token, new OrderKey(bin2hex(random_bytes(16))), new CreateOrderInputDto($oldQuote->quoteToken, $oldLines, $buyer, $consents)));
$check(0 === (int)$connection->query("SELECT COUNT(*) AS N FROM mf_order WHERE GROUP_PUBLIC_ID='{$groupId}'")->fetch()['N'], 'a paused group gets no order');
$proof[] = 'pause refuses quote and order';

// 5. The organizer adds a child set during the correction and finishes it with the current signature.
$shoot = (string)$connection->query("SELECT s.UF_PUBLIC_ID FROM b_hlbd_mf_group g JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE g.UF_PUBLIC_ID='{$groupId}'")->fetch()['UF_PUBLIC_ID'];
$mediaRevision = (int)$connection->query("SELECT m.REVISION FROM mf_media_shoot_state m JOIN b_hlbd_mf_shoot s ON s.ID=m.SHOOT_ID WHERE s.UF_PUBLIC_ID='{$shoot}'")->fetch()['REVISION'];
$services->get(AssignPhotosUseCase::class)->execute($organizer, $groupId, new MediaKey(bin2hex(random_bytes(16))), new AssignPhotosInputDto($shoot, $mediaRevision, [(string)$fixture['photoId']], 'C'));
$current = $read->execute($organizer, $groupId);
$finished = $finish->execute($organizer, $groupId, $linkKey(), new MarkupCorrectionFinishInputDto($current->revision, $current->signature));
$check(null === $finished->startedAt && null === $row()['UF_MARKUP_CORRECTION_AT'], 'sales resume after the finish');
$kinds = array_column($connection->query("SELECT KIND,CHAR_LENGTH(SIGNATURE) AS SIGNATURE FROM mf_group_link_history WHERE GROUP_ID={$before['ID']} ORDER BY ID")->fetchAll(), 'SIGNATURE', 'KIND');
$check(array_key_exists('markup_started', $kinds) && 64 === (int)$kinds['markup_finished'], 'link history records both events with the published signature');
$actions = array_column($connection->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(UF_DELTA,'$.action')) AS ACTION FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$before['ID']} ORDER BY ID")->fetchAll(), 'ACTION');
$check(['startMarkupCorrection', 'finishMarkupCorrection'] === array_values(array_intersect($actions, ['startMarkupCorrection', 'finishMarkupCorrection'])), 'organization journal of the correction');
$codes = array_map(static fn(object $child): string => $child->childCode, $gallery->resolve($token)->assignments);
$check(in_array('C', $codes, true), 'the gallery shows the corrected markup');
$proof[] = 'correction published';

// 6. The old quote is stale; a fresh quote places an order, and then the correction is closed for good.
$expect('QUOTE_STALE', static fn(): object => $orders->execute($token, new OrderKey(bin2hex(random_bytes(16))), new CreateOrderInputDto($oldQuote->quoteToken, $oldLines, $buyer, $consents)));
$freshQuote = $quotes->execute($token, $oldLines);
$orders->execute($token, new OrderKey(bin2hex(random_bytes(16))), new CreateOrderInputDto($freshQuote->quoteToken, $oldLines, $buyer, $consents));
$afterOrder = $read->execute($organizer, $groupId);
$check(!$afterOrder->markupCorrectable, 'a group with an order is no longer correctable');
$expect('GROUP_HAS_ORDERS', static fn(): object => $start->execute($organizer, $groupId, $linkKey(), new MarkupCorrectionStartInputDto($afterOrder->revision)));
$check(null === $row()['UF_MARKUP_CORRECTION_AT'], 'a refused start leaves the sales running');
$proof[] = 'stale quote, order, GROUP_HAS_ORDERS';

// 7. Migration replay keeps the data; the rollback refuses while the correction history exists.
require_once '/app/public/local/php_interface/migrations.foundation/Version20261011180001.php';
(new Version20261011180001())->up();
$check(null === $row()['UF_MARKUP_CORRECTION_AT'], 'replay keeps the group');
try {
    (new Version20261011180001())->down();
    $check(false, 'down() must refuse while the correction history exists');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'never rewritten'), 'rollback refusal: ' . $error->getMessage());
}
$proof[] = 'migration replay and guarded rollback';

echo 'D4 markup correction integration passed: ' . implode(', ', $proof) . PHP_EOL;
