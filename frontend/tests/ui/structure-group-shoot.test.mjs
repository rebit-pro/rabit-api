import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createAttempt, defaultShootId, fieldsFrom, hasDraftChanges, restorableDraft } from '../../src/modules/morefoto/structure/model.ts';

const shoot = (id, date) => ({
  id,
  institutionId: 'i',
  name: id,
  date,
  revision: 1
});

test('a new group on the institution page goes to the latest dated shoot', () => {
  assert.equal(defaultShootId([shoot('a', '2026-09-01'), shoot('b', '2026-09-14'), shoot('c', null)]), 'b');
  assert.equal(defaultShootId([shoot('a', null), shoot('b', null)]), 'a');
  assert.equal(defaultShootId([shoot('only', null)]), 'only');
  assert.equal(defaultShootId([]), '');
});

test('the chosen shoot becomes the collection of the new group', () => {
  const fields = { ...fieldsFrom(), name: 'Средняя группа' };
  const attempt = createAttempt({
    kind: 'group',
    parentId: 'shoot/2',
    id: null,
    revision: null,
    fields,
    base: fields,
    key: 'k',
    pending: null
  });
  assert.equal(attempt.method, 'POST');
  assert.equal(attempt.path, '/api/v1/shoots/shoot%2F2/groups');
  assert.deepEqual(attempt.body, {
    name: 'Средняя группа',
    groupKind: 'regular'
  });
});

test('a kept draft of a shoot is restored only for that shoot and with its own pending attempt', () => {
  const fields = { ...fieldsFrom(), name: 'Группа B' };
  const draft = {
    kind: 'group',
    parentId: 'b',
    id: null,
    revision: null,
    fields,
    base: { ...fieldsFrom() },
    key: 'a'.repeat(32),
    pending: null
  };
  const pending = { ...draft, pending: createAttempt(draft) };
  assert.equal(restorableDraft(draft, 'group', 'b', null), draft);
  assert.equal(restorableDraft(pending, 'group', 'b', null), pending);
  assert.equal(restorableDraft(draft, 'group', 'a', null), null);
  assert.equal(restorableDraft({ ...pending, pending: { ...pending.pending, key: 'b'.repeat(32) } }, 'group', 'b', null), null);
  assert.equal(restorableDraft(null, 'group', 'b', null), null);
});

test('an untouched draft is not kept and not restored, a changed or sent one is', () => {
  const fields = fieldsFrom();
  const untouched = {
    kind: 'shoot',
    parentId: 'i',
    id: null,
    revision: null,
    fields: { ...fields },
    base: { ...fields },
    key: 'a'.repeat(32),
    pending: null
  };
  assert.equal(hasDraftChanges(untouched), false);
  assert.equal(restorableDraft(untouched, 'shoot', 'i', null), null, 'an empty dialog closed without input has nothing to restore');

  for (const [name, value] of [
    ['name', 'Осень'],
    ['address', 'Воронеж'],
    ['date', '2026-10-01'],
    ['groupKind', 'staff']
  ]) {
    const changed = { ...untouched, fields: { ...fields, [name]: value } };
    assert.equal(hasDraftChanges(changed), true, name);
    assert.equal(restorableDraft(changed, 'shoot', 'i', null), changed, name);
  }

  const edited = { ...untouched, fields: { ...fields, name: 'Осень' } };
  const sent = { ...untouched, pending: createAttempt(edited) };
  assert.equal(hasDraftChanges(sent), true, 'a sent attempt without an answer is kept even with the initial fields');
  assert.equal(restorableDraft(sent, 'shoot', 'i', null), sent);

  const reordered = JSON.parse(JSON.stringify({ ...untouched, base: { groupKind: 'regular', date: '', address: '', name: '' } }));
  assert.equal(hasDraftChanges(reordered), false, 'the order of stored fields does not matter');
});
