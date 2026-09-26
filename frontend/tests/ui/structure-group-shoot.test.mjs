import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createAttempt, defaultShootId, fieldsFrom } from '../../src/modules/morefoto/structure/model.ts';

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
