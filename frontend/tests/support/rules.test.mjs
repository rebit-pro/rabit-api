import test from 'node:test';
import assert from 'node:assert/strict';
import {
  deliveryLabel,
  formatMoment,
  hasUnreadReply,
  isQuestionKey,
  lastCuratorReplyId,
  mayHaveBeenStored,
  newRequestId,
  normalizeMessage,
  parsePendingAsk,
  pendingStorageKey,
  questionProblemMessage,
  questionStorageKey,
  textProblem
} from '../../src/modules/morefoto/support/rules.ts';

const message = (id, author) => ({
  id,
  author,
  authorName: author,
  text: 't',
  createdAt: '2026-09-25T12:00:00Z',
  delivery: author === 'curator' ? null : 'sending'
});

test('the question key is kept per gallery link and must be 64 lowercase hex', () => {
  assert.equal(questionStorageKey('abc'), 'morefoto:live:question:v1:abc');
  assert.equal(isQuestionKey('a'.repeat(64)), true);
  for (const wrong of ['A'.repeat(64), 'a'.repeat(63), null, 42]) assert.equal(isQuestionKey(wrong), false);
});

test('texts follow the server limits before sending', () => {
  assert.equal(textProblem('Мария', 'Вопрос'), null);
  assert.equal(textProblem(null, 'Вопрос'), null);
  assert.match(textProblem('   ', 'Вопрос'), /обращаться/);
  assert.match(textProblem('я'.repeat(61), 'Вопрос'), /60/);
  assert.match(textProblem(null, ' \n '), /Напишите/);
  assert.match(textProblem(null, 'я'.repeat(2001)), /2000/);
  assert.equal(normalizeMessage('\r\n Строка\r\n'), 'Строка');
});

test('a new curator answer is unread until its id was seen', () => {
  const messages = [message(1, 'parent'), message(2, 'curator'), message(3, 'parent'), message(4, 'curator')];
  assert.equal(lastCuratorReplyId(messages), 4);
  assert.equal(hasUnreadReply(messages, 2), true);
  assert.equal(hasUnreadReply(messages, 4), false);
  assert.equal(hasUnreadReply([message(1, 'parent')], 0), false);
});

test('only an unknown outcome keeps the Idempotency-Key for a repeat', () => {
  assert.equal(mayHaveBeenStored({ status: null, code: '', network: true }), true);
  assert.equal(mayHaveBeenStored({ status: 503, code: 'SUPPORT_UNAVAILABLE', network: false }), true);
  assert.equal(mayHaveBeenStored({ status: 422, code: 'INVALID_QUESTION_MESSAGE', network: false }), false);
  assert.equal(mayHaveBeenStored({ status: 429, code: 'RATE_LIMITED', network: false }), false);
  assert.match(questionProblemMessage({ status: 429, code: 'RATE_LIMITED', network: false }), /через час/);
  assert.match(questionProblemMessage({ status: null, code: '', network: true }), /Текст сохранён/);
  assert.match(questionProblemMessage({ status: 403, code: 'FORBIDDEN', network: false }), /заведующей и воспитателям/);
});

test('delivery states and moments are readable', () => {
  assert.equal(deliveryLabel('delivered'), 'Доставлено куратору');
  assert.equal(deliveryLabel('sending'), 'Отправляется куратору');
  assert.equal(deliveryLabel(null), '');
  assert.equal(formatMoment('not a date'), '');
  assert.match(formatMoment('2026-09-25T09:05:00Z', new Date('2026-09-25T12:00:00Z')), /^\d{2}:\d{2}$/);
  assert.match(formatMoment('2026-09-20T09:05:00Z', new Date('2026-09-25T12:00:00Z')), /сентября/);
  assert.equal(
    newRequestId(() => '0123-4567'),
    '01234567'
  );
});

test('an unfinished first question is replayed only when intact', () => {
  const pending = { name: 'Мария', text: 'Вопрос', requestId: 'a'.repeat(32) };
  assert.equal(pendingStorageKey('abc'), 'morefoto:live:question-pending:v1:abc');
  assert.deepEqual(parsePendingAsk(JSON.stringify(pending)), pending);
  for (const broken of [
    null,
    '',
    '{',
    JSON.stringify({ ...pending, requestId: 'x' }),
    JSON.stringify({ ...pending, name: '' }),
    JSON.stringify({ ...pending, text: 1 })
  ]) {
    assert.equal(parsePendingAsk(broken), null);
  }
});

test('an unknown delivery is not shown as still sending', () => {
  assert.match(deliveryLabel('unknown'), /Не удалось подтвердить доставку/);
  assert.notEqual(deliveryLabel('unknown'), deliveryLabel('sending'));
});
