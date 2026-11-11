<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Production\Application\Institution\UseCase\RunInstitutionProductionUseCase;
use Morefoto\Production\Infrastructure\Id\ProductionIdGenerator;
use Morefoto\Commerce\Application\Order\Service\OrderAccessKeys;
use Morefoto\Commerce\Application\Order\Contract\CheckoutKeySealInterface;

/**
 * Isolated historical order fixtures copied from the real J4 paid snapshot, with their own quote and money fact.
 * They intentionally predate automatic email delivery (PAID_RECORDED_AT=NULL), never call a payment/mail provider,
 * and leave the original browser orders, groups and receipt trail unchanged.
 *
 * @return array<string, mixed>
 */
function institutionProductionFixture(string $action, string $sourceGroup): array
{
    if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
        throw new RuntimeException('Institution fixture requires the disposable test runtime.');
    }
    $db = Application::getConnection();
    $sql = $db->getSqlHelper();
    $uuid = static fn(): string => new ProductionIdGenerator()->uuid();
    $text = static fn(string $value): string => "'" . $sql->forSql($value) . "'";
    /** @param array<string, mixed> $row @param array<string, mixed> $changes */
    $clone = static function(string $table, array $row, array $changes) use ($db, $text): int {
        unset($row['ID']);
        foreach ($changes as $name => $value) {
            if (!array_key_exists($name, $row)) {
                throw new RuntimeException('Unknown fixture column ' . $table . '.' . $name);
            }
            $row[$name] = $value;
        }
        $columns = implode(',', array_map(static fn(string $name): string => '`' . $name . '`', array_keys($row)));
        $values = implode(',', array_map(static fn(mixed $value): string => null === $value ? 'NULL' : $text((string)$value), array_values($row)));
        $db->queryExecute('INSERT INTO ' . $table . '(' . $columns . ') VALUES(' . $values . ')');

        return (int)$db->getInsertedId();
    };
    $statePath = '/runtime/institution-print-fixture.json';
    if ('institution-setup' === $action) {
        if (is_file($statePath)) {
            return json_decode((string)file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        }
        $group = $db->query('SELECT * FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID=' . $text($sourceGroup))->fetchRaw();
        if (false === $group) {
            throw new RuntimeException('Source print group is missing.');
        }
        $shoot = $db->query('SELECT * FROM b_hlbd_mf_shoot WHERE ID=' . (int)$group['UF_SHOOT_ID'])->fetchRaw();
        $institution = $db->query('SELECT * FROM b_hlbd_mf_institution WHERE ID=' . (int)$shoot['UF_INSTITUTION_ID'])->fetchRaw();
        $publicId = $uuid();
        $nativeId = $clone('b_hlbd_mf_institution', $institution, ['UF_PUBLIC_ID' => $publicId, 'UF_NAME' => 'Архив учреждения E2E']);
        $shootId = $uuid();
        $shootNative = $clone('b_hlbd_mf_shoot', $shoot, ['UF_PUBLIC_ID' => $shootId, 'UF_NAME' => 'Первая съёмка архива', 'UF_INSTITUTION_ID' => $nativeId]);
        $closed = gmdate('Y-m-d H:i:s', time() - 7200);
        $baseChanges = ['UF_SHOOT_ID' => $shootNative, 'UF_SENT_AT' => gmdate('Y-m-d H:i:s', time() - 8 * 86400),
            'UF_CLOSES_AT' => $closed, 'UF_DIGITAL_SALES_UNTIL' => gmdate('Y-m-d H:i:s', time() + 5 * 86400), 'UF_DELIVERY_DUE_AT' => gmdate('Y-m-d H:i:s', time() + 5 * 86400)];
        $groups = [];
        foreach ([1, 2] as $index) {
            $id = $uuid();
            $native = $clone('b_hlbd_mf_group', $group, array_replace($baseChanges, ['UF_PUBLIC_ID' => $id, 'UF_NAME' => 'Группа архива ' . $index]));
            $groups[] = ['id' => $id, 'nativeId' => $native];
        }
        $order = $db->query('SELECT * FROM mf_order WHERE GROUP_PUBLIC_ID=' . $text($sourceGroup) . " AND PAYMENT_STATUS='paid' ORDER BY ID DESC LIMIT 1")->fetchRaw();
        if (false === $order) {
            throw new RuntimeException('A real paid J4 order must precede the institution fixture.');
        }
        $curator = $db->query("SELECT ID FROM b_user WHERE EMAIL='curator@example.invalid'")->fetchRaw();
        $curatorId = (int)$curator['ID'];
        $db->queryExecute("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT)
            VALUES({$nativeId},'curator',{$curatorId},UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $state = ['institutionId' => $publicId, 'institutionNativeId' => $nativeId, 'shootId' => $shootId,
            'shootNativeId' => $shootNative, 'groups' => $groups, 'sourceGroup' => $sourceGroup, 'sourceOrderNativeId' => (int)$order['ID'], 'curatorId' => $curatorId];
        $state['orderIds'] = [];
        foreach ($groups as $target) {
            $state['orderIds'][] = institutionPrintOrder($state, $target, $clone, $uuid, $text);
        }
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        return $state;
    }
    if (!is_file($statePath)) {
        throw new RuntimeException('Set up the institution print fixture first.');
    }
    $state = json_decode((string)file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    if ('institution-run' === $action) {
        $result = ServiceLocator::getInstance()->get(RunInstitutionProductionUseCase::class)->execute(200);

        return ['scheduled' => $result->scheduled, 'built' => $result->built, 'failed' => $result->failed];
    }
    if ('institution-add-open' === $action) {
        $source = $db->query('SELECT * FROM b_hlbd_mf_shoot WHERE ID=' . (int)$state['shootNativeId'])->fetchRaw();
        $newShoot = $uuid();
        $newNative = $clone('b_hlbd_mf_shoot', $source, ['UF_PUBLIC_ID' => $newShoot, 'UF_NAME' => 'Новая съёмка архива']);
        $group = $db->query('SELECT * FROM b_hlbd_mf_group WHERE ID=' . (int)$state['groups'][0]['nativeId'])->fetchRaw();
        $id = $uuid();
        $native = $clone('b_hlbd_mf_group', $group, ['UF_PUBLIC_ID' => $id, 'UF_SHOOT_ID' => $newNative, 'UF_NAME' => 'Новая группа архива',
            'UF_CLOSES_AT' => gmdate('Y-m-d H:i:s', time() + 86400)]);
        $state['newGroup'] = ['id' => $id, 'nativeId' => $native];
        $state['newShootId'] = $newShoot;
        $state['newShootNativeId'] = $newNative;
        $state['orderIds'][] = institutionPrintOrder($state, $state['newGroup'], $clone, $uuid, $text);
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        return $state;
    }
    if ('institution-add-timely' === $action) {
        $state['orderIds'][] = institutionPrintOrder($state, $state['newGroup'], $clone, $uuid, $text);
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        return $state;
    }
    if ('institution-close-new' === $action) {
        $db->queryExecute('UPDATE b_hlbd_mf_group SET UF_CLOSES_AT=' . $text(gmdate('Y-m-d H:i:s', time() - 7200)) . ' WHERE ID=' . (int)$state['newGroup']['nativeId']);

        return ['closed' => true];
    }
    if (in_array($action, ['institution-revoke', 'institution-grant'], true)) {
        $id = (int)$state['institutionNativeId'];
        $curator = (int)$state['curatorId'];
        $db->queryExecute("DELETE FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID={$id} AND UF_ROLE='curator'");
        if ('institution-grant' === $action) {
            $db->queryExecute("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT)
                VALUES({$id},'curator',{$curator},UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        }

        return ['assigned' => 'institution-grant' === $action];
    }
    throw new RuntimeException('Unknown institution fixture action.');
}

/**
 * @param array<string, mixed> $state
 * @param array{
 *     id: string,
 *     nativeId: int,
 * } $target
 * @param Closure(string, array<string,mixed>, array<string,mixed>): int $clone
 * @param Closure(): string                                              $uuid
 * @param Closure(string): string                                        $text
 */
function institutionPrintOrder(array $state, array $target, Closure $clone, Closure $uuid, Closure $text): string
{
    $db = Application::getConnection();
    $sourceId = (int)$state['sourceOrderNativeId'];
    $order = $db->query('SELECT * FROM mf_order WHERE ID=' . $sourceId)->fetchRaw();
    $quote = $db->query('SELECT * FROM mf_cart_quote WHERE TOKEN_HASH=' . $text((string)$order['QUOTE_HASH']))->fetchRaw();
    $quoteHash = hash('sha256', $uuid());
    $createdAt = gmdate('Y-m-d H:i:s', time() - 14400);
    $clone('mf_cart_quote', $quote, ['TOKEN_HASH' => $quoteHash,
        'CREATED_AT' => gmdate('Y-m-d H:i:s', time() - 14460), 'EXPIRES_AT' => gmdate('Y-m-d H:i:s', time() - 13560)]);
    $orderPublicId = $uuid();
    $number = 'MF-I-' . substr(str_replace('-', '', $orderPublicId), 0, 12);
    $paidAt = gmdate('Y-m-d H:i:s', time() - 10800);
    $changes = ['PUBLIC_ID' => $orderPublicId, 'NUMBER' => $number, 'QUOTE_HASH' => $quoteHash,
        'INSTITUTION_ID' => (int)$state['institutionNativeId'], 'INSTITUTION_PUBLIC_ID' => (string)$state['institutionId'], 'INSTITUTION_NAME' => 'Архив учреждения E2E',
        'SHOOT_ID' => $state['newShootNativeId'] ?? $state['shootNativeId'], 'SHOOT_PUBLIC_ID' => $state['newShootId'] ?? $state['shootId'],
        'GROUP_ID' => $target['nativeId'], 'GROUP_PUBLIC_ID' => $target['id'], 'GROUP_NAME' => 'Группа архива',
        'PAID_AT' => $paidAt, 'LATE_PAYMENT' => 0, 'PAID_RECORDED_AT' => null, 'PRODUCTION_STATUS' => 'not-started', 'CREATED_AT' => $createdAt, 'UPDATED_AT' => $paidAt];
    $orderId = $clone('mf_order', $order, $changes);
    $issued = ServiceLocator::getInstance()->get(OrderAccessKeys::class)->issue($orderId, new DateTimeImmutable($createdAt, new DateTimeZone('UTC')), 'checkout');
    $idempotencyKey = bin2hex(random_bytes(16));
    $receipt = $db->query('SELECT * FROM mf_order_checkout WHERE ORDER_ID=' . $sourceId)->fetchRaw();
    $clone('mf_order_checkout', $receipt, ['ORDER_ID' => $orderId, 'KEY_HASH' => hash('sha256', 'morefoto.order.checkout|' . $idempotencyKey),
        'REQUEST_HASH' => hash('sha256', $uuid()), 'CREATED_AT' => $createdAt,
        'SEALED_KEY' => ServiceLocator::getInstance()->get(CheckoutKeySealInterface::class)->seal($issued->key, $idempotencyKey, $order['GALLERY_HASH'] . '|' . $orderId)]);
    $consents = $db->query("SELECT * FROM mf_legal_consent WHERE CONTEXT='order' AND SUBJECT_ID=" . $sourceId);
    while (false !== ($consent = $consents->fetchRaw())) {
        $clone('mf_legal_consent', $consent, ['SUBJECT_ID' => $orderId, 'ACCEPTED_AT' => $createdAt]);
    }
    $lines = $db->query('SELECT * FROM mf_order_line WHERE ORDER_ID=' . $sourceId . ' ORDER BY LINE_NO');
    while (false !== ($line = $lines->fetchRaw())) {
        $clone('mf_order_line', $line, ['PUBLIC_ID' => $uuid(), 'ORDER_ID' => $orderId]);
    }
    $fact = $db->query('SELECT * FROM mf_payment_fact WHERE ORDER_ID=' . $sourceId . ' LIMIT 1')->fetchRaw();
    $attempt = $db->query('SELECT * FROM mf_payment_attempt WHERE ID=' . (int)$fact['ATTEMPT_ID'])->fetchRaw();
    $providerPaymentId = 'e2e-institution-' . $uuid();
    $attemptId = $clone('mf_payment_attempt', $attempt, ['PUBLIC_ID' => $uuid(), 'ORDER_ID' => $orderId, 'ORDER_PUBLIC_ID' => $orderPublicId,
        'ORDER_NUMBER' => $number, 'INSTITUTION_ID' => (int)$state['institutionNativeId'], 'INSTITUTION_NAME' => 'Архив учреждения E2E',
        'PROVIDER' => 'e2e-fixture', 'PROVIDER_PAYMENT_ID' => $providerPaymentId, 'PROVIDER_KEY' => $uuid(), 'CLIENT_KEY_HASH' => hash('sha256', $uuid()),
        'REQUEST_HASH' => hash('sha256', $uuid()), 'ACTIVE_ORDER_ID' => null, 'PRECEDING_ID' => null, 'PAID_AT' => $paidAt, 'LATE_PAYMENT' => 0, 'RECEIPT' => null,
        'CREATED_AT' => $createdAt, 'UPDATED_AT' => $paidAt, 'LAST_CHECK_AT' => $paidAt, 'NEXT_CHECK_AT' => null]);
    $clone('mf_payment_fact', $fact, ['ATTEMPT_ID' => $attemptId, 'ORDER_ID' => $orderId, 'PROVIDER' => 'e2e-fixture', 'PROVIDER_PAYMENT_ID' => $providerPaymentId,
        'PAID_AT' => $paidAt, 'LATE_PAYMENT' => 0, 'CREATED_AT' => $paidAt]);

    return $orderPublicId;
}
