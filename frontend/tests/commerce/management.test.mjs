import test from 'node:test';
import assert from 'node:assert/strict';
import {
  checkedPrice,
  productErrors,
  productValue,
  conditionsErrors,
  userErrors,
  applyUser,
  replacementNames,
  assignmentSignature
} from '../../src/modules/morefoto/management/rules.ts';
const product = {
  id: 'print',
  name: 'Отпечаток',
  description: 'Фото на бумаге',
  kind: 'physical',
  price: 10000,
  printCount: 1,
  active: true,
  staffDiscount: true
};
const catalog = {
  revision: 1,
  products: [
    product,
    { ...product, id: 'digital', name: 'Файл', kind: 'digital', printCount: 0 },
    { ...product, id: 'bundle', name: 'Все файлы', kind: 'bundle', printCount: 0 }
  ],
  giftThreshold: 200000,
  giftForStaff: false
};
const pc = () => ({
  kind: 'product',
  revision: 1,
  requestId: 'p',
  product: { ...product, price: '100', printCount: '1', format: '10 × 15', unit: 'шт.' }
});
const uc = () => ({
  kind: 'user',
  id: 2,
  name: 'Сотрудник',
  email: 'user@example.test',
  role: 'teacher',
  active: true,
  institutionIds: [],
  groupIds: [],
  replaceAssignments: false
});
const state = () => ({
  users: [
    { id: 1, name: 'Админ', email: 'admin@example.test', role: 'organizer', active: true, revision: 1, accessRevision: 1 },
    { id: 2, name: 'Сотрудник', email: 'user@example.test', role: 'teacher', active: true, revision: 1, accessRevision: 1 },
    { id: 3, name: 'Коллега', email: 'other@example.test', role: 'teacher', active: true, revision: 1, accessRevision: 1 }
  ],
  institutions: [
    { id: 'a', curatorId: null, headId: null, revision: 1 },
    { id: 'b', curatorId: null, headId: null, revision: 1 }
  ],
  shoots: [{ id: 's', institutionId: 'a' }],
  groups: [
    { id: 'g1', shootId: 's', teacherId: 2, revision: 1 },
    { id: 'g2', shootId: 's', teacherId: 3, revision: 1 }
  ],
  operations: [],
  userOperations: []
});
test('R09 money accepts zero and kopecks, rejects negative, exponent, extra decimals and excessive price', () => {
  assert.equal(checkedPrice('1 250,50'), 125050);
  assert.equal(checkedPrice('0'), 0);
  for (const value of ['-1', '1e3', '12.345', '1000000.01', 'NaN', '']) assert.equal(checkedPrice(value), null);
});
test('R09 physical units retain print count and digital units cannot become printed', () => {
  const c = pc();
  c.product.printCount = '2';
  assert.deepEqual(productErrors(c, catalog), {});
  assert.equal(productValue(c).printCount, 2);
  c.product.printCount = '0';
  assert.ok(productErrors(c, catalog).printCount);
  c.product = { ...c.product, id: 'digital', kind: 'digital', printCount: '99', name: 'Файл' };
  assert.equal(productValue(c).printCount, 0);
});
test('R09 stable product kinds, unique names and single digital bundle protect existing carts', () => {
  const c = pc();
  c.product.kind = 'bundle';
  assert.ok(productErrors(c, catalog).kind);
  c.product = { ...c.product, id: 'new', name: 'Все файлы' };
  assert.ok(productErrors(c, catalog).kind);
  assert.ok(productErrors(c, catalog).name);
});
test('R09 gift zero requires disabling offer, inherited settings ignore hidden stale fields', () => {
  const c = {
    groupId: null,
    inherit: false,
    products: catalog.products.map((p) => ({ ...p, price: '100' })),
    giftEnabled: true,
    giftThreshold: '0'
  };
  assert.ok(conditionsErrors(c, catalog).giftThreshold);
  c.giftEnabled = false;
  assert.deepEqual(conditionsErrors(c, catalog), {});
  c.products.pop();
  assert.ok(conditionsErrors(c, catalog).products);
  c.groupId = 'g';
  c.inherit = true;
  assert.deepEqual(conditionsErrors(c, catalog), {});
});
test('R09 user identity and active organizer are validated independently of UI', () => {
  const s = state(),
    c = uc();
  c.email = ' ADMIN@EXAMPLE.TEST ';
  assert.ok(userErrors(c, s, 1).email);
  c.id = 1;
  c.role = 'teacher';
  assert.ok(userErrors(c, s, 1).role);
  assert.ok(userErrors(c, s, 3).role);
});
test('R09 assigning occupied groups requires explicit acknowledgement', () => {
  const s = state(),
    c = uc();
  c.groupIds = ['g2'];
  assert.deepEqual(replacementNames(c, s), ['Коллега']);
  assert.ok(userErrors(c, s, 1).scope);
  c.replaceAssignments = true;
  assert.deepEqual(userErrors(c, s, 1), {});
  applyUser(c, s, 2);
  assert.equal(s.groups[0].teacherId, null);
  assert.equal(s.groups[1].teacherId, 2);
  assert.equal(s.groups[1].revision, 2);
});
test('R09 role switch moves assignments atomically and revokes previous session revision', () => {
  const s = state(),
    c = uc(),
    before = assignmentSignature(s);
  c.role = 'curator';
  c.institutionIds = ['a'];
  applyUser(c, s, 2);
  assert.equal(s.groups[0].teacherId, null);
  assert.equal(s.institutions[0].curatorId, 2);
  assert.equal(s.users[1].accessRevision, 2);
  assert.notEqual(assignmentSignature(s), before);
});
test('R09 disabling removes scope, while name-only edit preserves session validity', () => {
  const s = state(),
    c = uc();
  c.name = 'Новое имя';
  c.groupIds = ['g1'];
  applyUser(c, s, 2);
  assert.equal(s.users[1].accessRevision, 1);
  c.active = false;
  applyUser(c, s, 2);
  assert.equal(s.users[1].accessRevision, 2);
  assert.equal(s.groups[0].teacherId, null);
});
test('R09 unknown assignments cannot grant access', () => {
  const c = uc();
  c.groupIds = ['foreign'];
  assert.ok(userErrors(c, state(), 1).scope);
});
