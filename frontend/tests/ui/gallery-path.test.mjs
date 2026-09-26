import { test } from 'node:test';
import assert from 'node:assert/strict';
import { galleryPath } from '../../src/modules/morefoto/handoff/galleryPath.ts';

const path = (facts) => galleryPath({ kind: 'regular', problems: [], prepared: false, sentAt: null, ...facts });
const states = (steps) => Object.fromEntries(steps.map((step) => [step.key, step.state]));

test('a group without frames starts with the upload and shows every other problem', () => {
  const steps = path({ problems: ['noPhotos', 'noProducts'] });
  assert.deepEqual(states(steps), { photos: 'current', assign: 'todo', conditions: 'todo', prepare: 'todo', transmit: 'todo' });
  assert.match(steps[0].hint, /нет готовых кадров/);
  assert.match(steps[2].hint, /продукции/);
});

test('unassigned frames make the distribution the current step', () => {
  assert.deepEqual(states(path({ problems: ['unassignedPhotos'] })), {
    photos: 'done',
    assign: 'current',
    conditions: 'done',
    prepare: 'todo',
    transmit: 'todo'
  });
});

test('a ready group waits for the link check, then for the transmission', () => {
  const ready = path({});
  assert.equal(ready.find((step) => step.state === 'current')?.key, 'prepare');
  const prepared = path({ prepared: true });
  assert.equal(prepared.find((step) => step.state === 'current')?.key, 'transmit');
  assert.match(prepared.at(-1).hint, /только после этой отметки/);
});

test('a transmitted group has every step done', () => {
  assert.ok(path({ prepared: true, sentAt: '2026-09-26T10:00:00Z' }).every((step) => step.state === 'done' && !step.hint));
});

test('only a staff group has the staff lists step', () => {
  assert.equal(
    path({}).some((step) => step.key === 'staff'),
    false
  );
  const staff = galleryPath({ kind: 'staff', problems: ['staffRequestsPending'], prepared: false, sentAt: null });
  assert.equal(staff.find((step) => step.state === 'current')?.key, 'staff');
});
