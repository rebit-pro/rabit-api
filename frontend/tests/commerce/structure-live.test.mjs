import test from 'node:test';
import assert from 'node:assert/strict';
import { createAttempt, fieldsFrom, refreshDraft, validateFields } from '../../src/modules/morefoto/structure/model.ts';
const base = {
  id: 'shoot-id',
  institutionId: 'institution-id',
  name: 'Осень',
  date: '2026-10-01',
  revision: 4
};
function draft(item = base) {
  const fields = fieldsFrom(item);
  return {
    kind: 'shoot',
    parentId: 'institution-id',
    id: item.id,
    revision: item.revision,
    fields: { ...fields },
    base: fields,
    key: 'a'.repeat(32),
    pending: null
  };
}
test('shoot PATCH preserves an omitted date and clears an explicitly emptied date', () => {
  const value = draft();
  value.fields.name = 'Зима';
  assert.deepEqual(createAttempt(value).body, { name: 'Зима', revision: 4 });
  value.fields.date = '';
  assert.deepEqual(createAttempt(value).body, {
    name: 'Зима',
    date: null,
    revision: 4
  });
});
test('new shoot may have an unassigned date; group edit never sends immutable kind or teacher scope', () => {
  const value = draft();
  value.id = null;
  value.revision = null;
  value.fields.date = '';
  assert.deepEqual(createAttempt(value).body, { name: 'Осень', date: null });
  value.kind = 'group';
  value.id = 'group-id';
  value.revision = 3;
  assert.deepEqual(createAttempt(value).body, { name: 'Осень', revision: 3 });
  assert.equal(createAttempt(value).path, '/api/v1/groups/group-id');
});
test('captured attempt remains unchanged for uncertain-result retry', () => {
  const value = draft();
  const captured = createAttempt(value);
  value.fields.name = 'Later input';
  value.fields.date = '';
  value.key = 'b'.repeat(32);
  assert.equal(captured.body.name, 'Осень');
  assert.equal(captured.key, 'a'.repeat(32));
  assert.equal(Object.hasOwn(captured.body, 'date'), false);
});
test('explicit conflict reload adopts the current server fields and revision', () => {
  const value = draft();
  value.fields.name = 'Моя правка';
  value.fields.date = '';
  const refreshed = refreshDraft(value, { ...base, name: 'Чужая правка', date: '2026-11-02', revision: 5 }, 'b'.repeat(32));
  assert.equal(value.fields.name, 'Моя правка');
  assert.equal(refreshed.fields.name, 'Чужая правка');
  assert.equal(refreshed.fields.date, '2026-11-02');
  assert.equal(refreshed.revision, 5);
  assert.equal(refreshed.key, 'b'.repeat(32));
  assert.deepEqual(createAttempt(refreshed).body, {
    name: 'Чужая правка',
    revision: 5
  });
});
test('client rejects invalid calendar dates while accepting leap day and nullable dates', () => {
  for (const date of ['2026-02-29', '2026-04-31', '2026-1-01', '0000-01-01'])
    assert.ok(validateFields('shoot', { ...fieldsFrom(base), date }).date);
  for (const date of ['', '2028-02-29']) assert.equal(validateFields('shoot', { ...fieldsFrom(base), date }).date, undefined);
});
