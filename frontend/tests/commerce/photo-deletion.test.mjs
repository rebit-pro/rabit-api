import test from 'node:test';
import assert from 'node:assert/strict';
import { createPhotoDeletion } from '../../src/modules/morefoto/photos/deletion.ts';

/** Axios-shaped failures: no response is a lost answer, a response carries the API error code. */
const lost = () => Object.assign(new Error('Network Error'), { isAxiosError: true, response: undefined });
const answered = (status, code) =>
  Object.assign(new Error('Request failed'), { isAxiosError: true, response: { status, data: { error: { code } } } });

/** The server of DeleteGroupPhotosUseCase: the key replays its stored result before the revision is compared. */
function stand(revision = 5) {
  const state = { revision, photos: new Set(['p1', 'p2', 'p3']), stored: new Map(), deletions: 0, calls: [] };
  let loseNext = false;
  let failNext = null;
  const server = (attempt) => {
    const payload = JSON.stringify([attempt.revision, attempt.photoIds]);
    const stored = state.stored.get(attempt.key);
    if (stored) {
      if (stored.payload !== payload) throw answered(409, 'IDEMPOTENCY_CONFLICT');
      return stored.result;
    }
    if (attempt.revision !== state.revision) throw answered(409, 'REVISION_CONFLICT');
    if (!attempt.photoIds.every((id) => state.photos.has(id))) throw answered(409, 'PHOTO_NOT_DELETABLE');
    for (const id of attempt.photoIds) state.photos.delete(id);
    state.deletions++;
    const result = { deleted: attempt.photoIds.length, revision: ++state.revision };
    state.stored.set(attempt.key, { payload, result });
    return result;
  };
  let keys = 0;
  const deletion = createPhotoDeletion({
    async send(attempt) {
      state.calls.push(structuredClone(attempt));
      if (failNext) {
        const failure = failNext;
        failNext = null;
        throw failure;
      }
      const result = server(attempt);
      if (loseNext) {
        loseNext = false;
        throw lost();
      }
      return result;
    },
    newKey: () => 'key-' + ++keys
  });
  return { state, deletion, loseNextAnswer: () => (loseNext = true), failNext: (failure) => (failNext = failure) };
}

test('#115: сервер удалил кадры, ответ потерян — повтор с тем же ключом получает сохранённый успех', async () => {
  const { state, deletion, loseNextAnswer } = stand();
  loseNextAnswer();

  await assert.rejects(deletion.run('g1', 5, ['p1', 'p2']), /Network Error/);
  assert.equal(deletion.pending(), true);
  // The screen may have reloaded meanwhile and hold the new revision: the repeat still sends the original payload.
  const result = await deletion.run('g1', 6, ['p2', 'p1']);

  assert.deepEqual(result, { deleted: 2, revision: 6 });
  assert.equal(deletion.pending(), false);
  assert.equal(state.deletions, 1);
  assert.equal(state.calls.length, 2);
  assert.deepEqual(state.calls[1], state.calls[0]);
  assert.deepEqual(state.calls[0], { groupId: 'g1', revision: 5, photoIds: ['p1', 'p2'], key: 'key-1' });
});

test('#115: определённый 409 завершает попытку — следующее удаление с новым ключом и текущей ревизией', async () => {
  const { state, deletion } = stand(7);

  await assert.rejects(deletion.run('g1', 5, ['p1']), (cause) => cause.response.data.error.code === 'REVISION_CONFLICT');
  assert.equal(deletion.pending(), false);
  assert.deepEqual(await deletion.run('g1', 7, ['p1']), { deleted: 1, revision: 8 });

  assert.deepEqual(
    state.calls.map((call) => [call.key, call.revision]),
    [
      ['key-1', 5],
      ['key-2', 7]
    ]
  );
});

test('#115: ответ 5xx не доказывает отказ — повтор идёт с тем же ключом', async () => {
  const { state, deletion, failNext } = stand();
  failNext(answered(502, ''));

  await assert.rejects(deletion.run('g1', 5, ['p3']));
  assert.equal(deletion.pending(), true);
  await deletion.run('g1', 5, ['p3']);

  assert.deepEqual(
    state.calls.map((call) => call.key),
    ['key-1', 'key-1']
  );
  assert.equal(state.deletions, 1);
});

test('#115: отмена диалога забывает попытку, новый набор не продолжает прежний', async () => {
  const { state, deletion, loseNextAnswer } = stand();
  loseNextAnswer();
  await assert.rejects(deletion.run('g1', 5, ['p1']));

  deletion.forget();
  assert.equal(deletion.pending(), false);
  // After the cancel the list was reloaded with revision 6; p1 is gone, the organizer deletes another frame.
  await deletion.run('g1', 6, ['p2']);
  loseNextAnswer();
  await assert.rejects(deletion.run('g1', 7, ['p3']));
  await deletion.run('g2', 8, ['p3']).catch(() => undefined);

  assert.deepEqual(
    state.calls.map((call) => [call.groupId, call.key, call.photoIds.join()]),
    [
      ['g1', 'key-1', 'p1'],
      ['g1', 'key-2', 'p2'],
      ['g1', 'key-3', 'p3'],
      ['g2', 'key-4', 'p3']
    ]
  );
});
