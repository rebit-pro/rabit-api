import test from 'node:test';
import assert from 'node:assert/strict';
import { sendBeforeKey } from '../../src/modules/morefoto/support/firstQuestion.ts';

/** An idempotent server: the same key with the same payload replays, another payload conflicts. */
function stand() {
  const questions = new Map();
  const calls = [];
  const storage = { pending: null, key: null };
  let loseNextAnswer = false;
  const failure = (status, network = false) => Object.assign(new Error('failed'), { problem: { status, code: '', network } });
  const ports = {
    async ask(name, text, requestId) {
      calls.push(['ask', name, text, requestId]);
      const known = questions.get(requestId);
      if (known && (known.name !== name || known.messages[0] !== text)) throw failure(409);
      const question = known ?? { key: 'k'.repeat(63) + questions.size, name, messages: [text] };
      questions.set(requestId, question);
      if (loseNextAnswer) {
        loseNextAnswer = false;
        throw failure(null, true);
      }
      return { id: 1, number: 1, questionKey: question.key, messages: [] };
    },
    async add(key, text, requestId) {
      calls.push(['add', key, text, requestId]);
      [...questions.values()].find((question) => question.key === key).messages.push(text);
      return { id: 1, number: 1, messages: [] };
    },
    problem: (cause) => cause.problem,
    readPending: () => storage.pending,
    writePending: (value) => (storage.pending = value),
    keep: (key) => (storage.key = key)
  };
  return { ports, questions, calls, storage, lose: () => (loseNextAnswer = true) };
}
const first = 'a'.repeat(32);
const second = 'b'.repeat(32);

test('a confirmed first question keeps its key and leaves nothing pending', async () => {
  const { ports, storage, questions } = stand();
  await sendBeforeKey(ports, 'Мария', 'Когда фото?', first);
  assert.equal(storage.pending, null);
  assert.equal(storage.key, questions.get(first).key);
});

test('after a lost answer an edited text recovers the first conversation and becomes its next reply', async () => {
  const { ports, storage, questions, calls, lose } = stand();
  lose();
  await assert.rejects(sendBeforeKey(ports, 'Мария', 'Когда фото?', first));
  assert.notEqual(storage.pending, null);

  await sendBeforeKey(ports, 'Мария', 'Когда фото? И можно крупнее?', second);

  assert.equal(questions.size, 1, 'no second conversation');
  assert.deepEqual(questions.get(first).messages, ['Когда фото?', 'Когда фото? И можно крупнее?']);
  assert.deepEqual(calls[1], ['ask', 'Мария', 'Когда фото?', first], 'the original payload is replayed first');
  assert.equal(storage.key, questions.get(first).key);
  assert.equal(storage.pending, null);
});

test('after a lost answer an edited name does not break the replay of the original question', async () => {
  const { ports, storage, questions, lose } = stand();
  lose();
  await assert.rejects(sendBeforeKey(ports, 'Мария', 'Когда фото?', first));

  await sendBeforeKey(ports, 'Мария Иванова', 'Когда фото?', first);

  assert.equal(questions.size, 1);
  assert.equal(questions.get(first).name, 'Мария');
  assert.deepEqual(questions.get(first).messages, ['Когда фото?']);
  assert.equal(storage.key, questions.get(first).key);
});

test('only a proven refusal of the original payload drops the unfinished attempt', async () => {
  for (const [status, kept] of [
    [409, true],
    [503, true],
    [null, true],
    [422, false],
    [429, false]
  ]) {
    const { ports, storage } = stand();
    ports.ask = async () => {
      throw Object.assign(new Error('failed'), { problem: { status, code: '', network: status === null } });
    };
    await assert.rejects(sendBeforeKey(ports, 'Мария', 'Когда фото?', first));
    assert.equal(storage.pending !== null, kept, 'status ' + status);
  }
});
