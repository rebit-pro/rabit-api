import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ImportAttempt } from '../../src/modules/morefoto/photos/import-attempt.ts';

const original = 'a'.repeat(32);
function memory() {
  const values = new Map();
  return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
}

test('TC-13: новый импорт переживает потерю ответа и перезагрузку, сохраняя свой ключ', async () => {
  const storage = memory();
  const first = new ImportAttempt(storage, 'user:shoot');
  assert.equal(first.current(original), original);
  const replacement = await first.restart(original);
  assert.notEqual(replacement, original);
  assert.equal(first.current(original), replacement);
  assert.equal(new ImportAttempt(storage, 'user:shoot').current(original), replacement);
  assert.equal(new ImportAttempt(storage, 'other-user:shoot').current(original), original);
  assert.equal(first.current('b'.repeat(32)), 'b'.repeat(32));
});

test('TC-15: повтор нового импорта в двух браузерах имеет один ключ; следующая попытка отличается', async () => {
  const left = new ImportAttempt(memory(), 'user:shoot');
  const right = new ImportAttempt(memory(), 'user:shoot');
  const [one, two] = await Promise.all([left.restart(original), right.restart(original)]);
  assert.equal(one, two);
  assert.notEqual(await left.restart(original), one);
});

test('TC-15: недоступное или повреждённое хранилище не меняет ключ сетевого повтора', async () => {
  const unavailable = {
    getItem: () => {
      throw new Error('unavailable');
    },
    setItem: () => {
      throw new Error('unavailable');
    }
  };
  for (const storage of [null, unavailable, { getItem: () => 'invalid', setItem: () => {} }]) {
    const attempt = new ImportAttempt(storage, 'user:shoot');
    assert.equal(attempt.current(original), original);
    const replacement = await attempt.restart(original);
    assert.equal(attempt.current(original), replacement);
  }
});
