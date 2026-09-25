<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\UseCase\DeliverQuestionMessageUseCase;
use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatMessageInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatSendOutputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;

// K3: real MySQL trail of the browser questions, delivery with a scripted MAX boundary and the webhook over the stand's nginx.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Support verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.support')) {
    throw new RuntimeException('Missing verification module.');
}
$connection = Application::getConnection();
$services = ServiceLocator::getInstance();
$chatId = (int)getenv('MOREFOTO_SUPPORT_MAX_CHAT_ID');
$secret = (string)getenv('MOREFOTO_SUPPORT_MAX_WEBHOOK_SECRET');
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('K3 verification failed: ' . $message);
    }
};
$column = static function(string $sql) use ($connection): array {
    $values = [];
    $result = $connection->query($sql);
    while (false !== ($row = $result->fetch())) {
        $values[] = (string)reset($row);
    }

    return $values;
};
/** @return array{0: int, 1: array<string, mixed>} */
$http = static function(string $method, string $path, ?array $body, array $headers): array {
    $handle = curl_init('http://backend' . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => null === $body ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $raw = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return [$status, is_string($raw) && '' !== $raw ? (array)json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : []];
};
$check(-72000000001 === $chatId && 32 <= strlen($secret), 'stand MAX configuration');
$secrets = json_decode((string)file_get_contents('/runtime/k3-questions.json'), true, 4, JSON_THROW_ON_ERROR);
$questionKey = (string)($secrets['questionKey'] ?? '');
$check(1 === preg_match('/^[a-f0-9]{64}$/D', $questionKey), 'browser recorded the question key');

// 1. The browser left one parent conversation reached only by the key hash, and one staff conversation.
$parent = $connection->query("SELECT ID,GROUP_ID,AUTHOR_NAME,CONTEXT FROM mf_support_question WHERE AUTHOR='parent' AND KEY_HASH='" . hash('sha256', $questionKey) . "'")->fetch();
$check(false !== $parent && 'K3 Мария' === $parent['AUTHOR_NAME'], 'parent conversation');
$parentId = (int)$parent['ID'];
$check(str_starts_with((string)$parent['CONTEXT'], 'Сад «'), 'gallery context for curators');
$check(['parent', 'parent'] === $column("SELECT AUTHOR FROM mf_support_message WHERE QUESTION_ID={$parentId} ORDER BY ID"), 'parent replies without duplicates');
$check(['pending', 'pending'] === $column("SELECT DELIVERY_STATUS FROM mf_support_message WHERE QUESTION_ID={$parentId} ORDER BY ID"), 'no worker runs on the stand');
$staff = $connection->query("SELECT q.ID FROM mf_support_question q JOIN b_user u ON u.ID=q.STAFF_USER_ID WHERE q.AUTHOR='staff' AND u.LOGIN='teacher@example.invalid'")->fetch();
$check(false !== $staff, 'teacher conversation');
$staffId = (int)$staff['ID'];
$check(['staff'] === $column("SELECT AUTHOR FROM mf_support_message WHERE QUESTION_ID={$staffId}"), 'teacher reply');
// A first question whose response the browser lost is replayed after reload with the same key: one conversation.
$check(['1'] === $column("SELECT COUNT(*) FROM mf_support_question WHERE AUTHOR='parent' AND AUTHOR_NAME='K3 Потерянный ответ'"), 'lost response created a second conversation');
$check(['1'] === $column("SELECT COUNT(*) FROM mf_support_message m JOIN mf_support_question q ON q.ID=m.QUESTION_ID WHERE q.AUTHOR_NAME='K3 Потерянный ответ'"), 'lost response duplicated the reply');
// An edited text typed after the lost answer is recovered into the first conversation as its next reply.
$check(['1'] === $column("SELECT COUNT(*) FROM mf_support_question WHERE AUTHOR='parent' AND AUTHOR_NAME='K3 Изменённый текст'"), 'edited text created a second conversation');
$check(['Исходный вопрос до потери ответа.', 'Исправленный текст после сбоя.'] === $column("SELECT m.BODY FROM mf_support_message m JOIN mf_support_question q ON q.ID=m.QUESTION_ID WHERE q.AUTHOR_NAME='K3 Изменённый текст' ORDER BY m.ID"), 'edited text replies');
foreach (['mf_support_question' => 'CONCAT(AUTHOR_NAME,CONTEXT,COALESCE(KEY_HASH,\'\'))', 'mf_support_message' => 'BODY', 'mf_support_idempotency' => 'COALESCE(SEALED_KEY,\'\')'] as $table => $text) {
    $check([] === $column("SELECT 1 FROM {$table} WHERE INSTR({$text}," . "'{$questionKey}')>0"), 'raw question key is stored in ' . $table);
}

// 2. Delivery through a scripted MAX boundary: mid is kept, a finished reply is never sent twice, failures stay visible.
$max = new class implements MaxChatMessengerInterface {
    /** @var list<MaxChatSendOutputDto> */
    public array $outcomes = [];
    /** @var list<MaxChatMessageInputDto> */
    public array $sent = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(MaxChatMessageInputDto $message): MaxChatSendOutputDto
    {
        $this->sent[] = $message;

        return array_shift($this->outcomes) ?? throw new LogicException('Unexpected MAX send.');
    }
};
$deliver = new DeliverQuestionMessageUseCase(
    $services->get(SupportTransactionInterface::class),
    $services->get(QuestionDeliveryRepositoryInterface::class),
    $max,
    $services->get(MaxQuestionTextBuilder::class),
    $services->get(QuestionDeliveryPublisherInterface::class),
    $services->get(ClockInterface::class),
    $chatId,
);
[$firstId, $secondId] = array_map('intval', $column("SELECT ID FROM mf_support_message WHERE QUESTION_ID={$parentId} ORDER BY ID"));
$staffMessageId = (int)$column("SELECT ID FROM mf_support_message WHERE QUESTION_ID={$staffId}")[0];
$max->outcomes = [
    new MaxChatSendOutputDto(MaxSendStatusEnum::DELIVERED, 'mid.k3.' . $firstId),
    new MaxChatSendOutputDto(MaxSendStatusEnum::RETRY, errorCode: 'max_http_429'),
    new MaxChatSendOutputDto(MaxSendStatusEnum::REJECTED, errorCode: 'max_http_403'),
];
$deliver->execute($firstId);
$deliver->execute($firstId);
$deliver->execute($secondId);
$deliver->execute($staffMessageId);
$check(3 === count($max->sent) && $chatId === $max->sent[0]->chatId, 'one send per claim');
$check(str_starts_with($max->sent[0]->text, 'Вопрос №' . $parentId . ' · родитель «K3 Мария»'), 'text for curators');
$check(str_starts_with($max->sent[2]->text, 'Вопрос №' . $staffId . ' · сотрудник «'), 'staff text for curators');
$check(['delivered', 'pending'] === $column("SELECT DELIVERY_STATUS FROM mf_support_message WHERE QUESTION_ID={$parentId} ORDER BY ID"), 'delivered and retry states');
$check(['mid.k3.' . $firstId] === $column("SELECT MAX_MID FROM mf_support_message WHERE ID={$firstId}"), 'outgoing mid');
$check(['max_http_429'] === $column("SELECT LAST_ERROR_CODE FROM mf_support_message WHERE ID={$secondId} AND NEXT_ATTEMPT_AT>UTC_TIMESTAMP()"), 'retry is scheduled later');
$check(['failed'] === $column("SELECT DELIVERY_STATUS FROM mf_support_message WHERE ID={$staffMessageId}"), 'rejected reply fails');

// 3. Webhook over HTTP: only the subscription secret, only replies to the bot message in the curator group, each mid once.
$reply = static fn(string $mid, string $replyTo, int $chat): array => [
    'update_type' => 'message_created',
    'timestamp' => 1790000000000,
    'message' => [
        'sender' => ['user_id' => 101, 'first_name' => 'Рита', 'last_name' => 'Смирнова', 'is_bot' => false, 'last_activity_time' => 1],
        'recipient' => ['chat_id' => $chat, 'chat_type' => 'chat'],
        'timestamp' => 1790000000000,
        'link' => ['type' => 'reply', 'message' => ['mid' => $replyTo, 'seq' => 1, 'text' => 'Вопрос']],
        'body' => ['mid' => $mid, 'seq' => 2, 'text' => 'Фотографии будут в пятницу.'],
    ],
    'user_locale' => 'ru',
];
[$status] = $http('POST', '/api/v1/webhooks/max/updates', $reply('mid.k3.reply', 'mid.k3.' . $firstId, $chatId), ['X-Max-Bot-Api-Secret: wrong-secret-0123456789abcdef0123']);
$check(401 === $status, 'wrong secret is rejected');
[$status] = $http('POST', '/api/v1/webhooks/max/updates', $reply('mid.k3.reply', 'mid.k3.' . $firstId, $chatId), []);
$check(401 === $status, 'missing secret is rejected');
foreach ([$reply('mid.k3.reply', 'mid.k3.' . $firstId, $chatId), $reply('mid.k3.reply', 'mid.k3.' . $firstId, $chatId), $reply('mid.k3.other', 'mid.k3.' . $firstId, -1)] as $update) {
    [$status, $body] = $http('POST', '/api/v1/webhooks/max/updates', $update, ['X-Max-Bot-Api-Secret: ' . $secret]);
    $check(200 === $status && true === ($body['data']['acknowledged'] ?? null), 'accepted update is acknowledged');
}
[$status] = $http('POST', '/api/v1/webhooks/max/updates', ['update_type' => 'bot_added', 'timestamp' => 1, 'chat_id' => -72000000002, 'user' => ['user_id' => 1, 'first_name' => 'A', 'is_bot' => false], 'is_channel' => false], ['X-Max-Bot-Api-Secret: ' . $secret]);
$check(200 === $status && ['1'] === $column('SELECT BOT_PRESENT FROM mf_support_max_chat WHERE CHAT_ID=-72000000002'), 'bot membership is remembered');
$check(['Рита Смирнова|Фотографии будут в пятницу.'] === $column("SELECT CONCAT(AUTHOR_NAME,'|',BODY) FROM mf_support_message WHERE QUESTION_ID={$parentId} AND AUTHOR='curator'"), 'one curator reply');

// 4. The parent sees the answer by the key only; the database refuses a curator reply without its MAX mid.
[$status, $body] = $http('GET', '/api/v1/public/questions/current', null, ['X-Question-Key: ' . $questionKey]);
$messages = $body['data']['messages'] ?? [];
$check(200 === $status && 3 === count($messages), 'parent history over HTTP');
$check(['delivered', 'sending', null] === array_column($messages, 'delivery') && 'curator' === $messages[2]['author'], 'delivery states and curator answer');
[$status] = $http('GET', '/api/v1/public/questions/current', null, ['X-Question-Key: ' . str_repeat('0', 64)]);
$check(404 === $status, 'foreign key is not found');
$accepted = true;
try {
    $connection->queryExecute("INSERT INTO mf_support_message(QUESTION_ID,AUTHOR,AUTHOR_NAME,BODY,CREATED_AT) VALUES({$parentId},'curator','X','Y',UTC_TIMESTAMP())");
} catch (Throwable) {
    $accepted = false;
}
$check(!$accepted, 'CHECK accepted a curator reply without mid');

echo 'K3 support integration passed' . PHP_EOL;
