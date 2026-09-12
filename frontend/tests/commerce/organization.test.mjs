import test from 'node:test';
import assert from 'node:assert/strict';
import { blankFields, scopedOrganization, validShootDate, validateEntity } from '../../src/modules/morefoto/organization/rules.ts';
const staff = [
  { id: 1, role: 'organizer' },
  { id: 2, role: 'curator' },
  { id: 3, role: 'head' },
  { id: 4, role: 'teacher' }
];
const state = {
  institutions: [
    { id: 'a', name: 'Сад', address: 'Улица, 10', curatorId: 2, headId: 3 },
    { id: 'b', name: 'Школа', address: 'Улица, 20', curatorId: null, headId: null }
  ],
  shoots: [
    { id: 's1', institutionId: 'a', name: 'Осень', date: '2026-09-10' },
    { id: 's2', institutionId: 'a', name: 'Осень', date: '2027-09-10' },
    { id: 's3', institutionId: 'b', name: 'Осень', date: '2026-09-10' }
  ],
  groups: [
    { id: 'g1', institutionId: 'a', shootId: 's1', name: 'Звёздочки', kind: 'regular', teacherId: 4 },
    { id: 'g2', institutionId: 'a', shootId: 's2', name: 'Сотрудники', kind: 'staff', teacherId: null },
    { id: 'g3', institutionId: 'b', shootId: 's3', name: '1А', kind: 'regular', teacherId: 4 }
  ],
  operations: []
};
const command = (kind, fields, parentId) => ({
  kind,
  parentId,
  requestId: 'request',
  revision: null,
  fields: { ...blankFields(), ...fields }
});
test('Дата съёмки проверяется по календарю, включая високосный год', () => {
  assert.equal(validShootDate('2028-02-29'), true);
  for (const date of ['2026-02-29', '2026-04-31', '2026-13-01', '2026-9-1', '', '1999-12-31']) assert.equal(validShootDate(date), false);
});
test('Повторная съёмка допускается в другую дату и в другом учреждении', () => {
  assert.equal(
    validateEntity(command('shoot', { name: '  ОСЕНЬ ', date: '2026-09-10' }, 'a'), state, staff).name?.includes('уже существует'),
    true
  );
  assert.deepEqual(validateEntity(command('shoot', { name: 'Осень', date: '2028-09-10' }, 'a'), state, staff), {});
  assert.deepEqual(validateEntity(command('shoot', { name: 'Осень', date: '2027-09-10' }, 'b'), state, staff), {});
});
test('Дубликат учреждения учитывает название и адрес, существующая запись может редактироваться', () => {
  const c = command('institution', { name: 'САД', address: ' Улица, 10 ' });
  assert.ok(validateEntity(c, state, staff).name);
  assert.deepEqual(validateEntity({ ...c, id: 'a' }, state, staff), {});
  assert.deepEqual(validateEntity(command('institution', { name: 'Сад', address: 'Улица, 11' }), state, staff), {});
});
test('Названия групп и единственная папка сотрудников ограничены одной съёмкой', () => {
  assert.ok(validateEntity(command('group', { name: 'Звёздочки' }, 's1'), state, staff).name);
  assert.deepEqual(validateEntity(command('group', { name: 'Звёздочки' }, 's2'), state, staff), {});
  assert.ok(validateEntity(command('group', { name: 'Персонал', groupKind: 'staff' }, 's2'), state, staff).groupKind);
});
test('Назначение требует существующего сотрудника подходящей роли', () => {
  assert.ok(validateEntity(command('institution', { name: 'Новый сад', address: 'Улица, 30', curatorId: 4 }), state, staff).curatorId);
  assert.ok(validateEntity(command('group', { name: 'Новая группа', teacherId: 999 }, 's1'), state, staff).teacherId);
});
test('Область куратора и руководителя — учреждение; ответственного — только его группы', () => {
  assert.deepEqual(
    scopedOrganization(state, staff[1]).institutions.map((x) => x.id),
    ['a']
  );
  assert.deepEqual(
    scopedOrganization(state, staff[2]).groups.map((x) => x.id),
    ['g1', 'g2']
  );
  const teacher = scopedOrganization(state, staff[3]);
  assert.deepEqual(
    teacher.groups.map((x) => x.id),
    ['g1', 'g3']
  );
  assert.deepEqual(
    teacher.shoots.map((x) => x.id),
    ['s1', 's3']
  );
  assert.equal('curatorId' in teacher.institutions[0], false);
  assert.equal('teacherId' in teacher.groups[0], false);
  assert.equal(scopedOrganization(state, { id: 999, role: 'head' }).institutions.length, 0);
  assert.equal(scopedOrganization(state, staff[0]).groups.length, 3);
});
