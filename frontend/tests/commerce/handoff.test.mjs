import test from 'node:test';
import assert from 'node:assert/strict';
import {
  calendarDays,
  parseTransmission,
  moscowInput,
  currentGroupState,
  groupSentAt,
  resolveCode,
  requestRows,
  reviewRequest,
  preparationProblems,
  preparationSignature,
  serverMoment,
  liveLinkErrors,
  transferPreviewFromServer,
  staffTransferErrorText
} from '../../src/modules/morefoto/handoff/rules.ts';
import { problemText } from '../../src/modules/morefoto/handoff/display.ts';
const groups = [
  { id: 'g', institutionId: 'i', shootId: 's', kind: 'regular', state: 'preparing', teacherId: 104 },
  { id: 'g2', institutionId: 'i', shootId: 's', kind: 'regular' },
  { id: 't', institutionId: 'i', shootId: 's', kind: 'staff' }
];
const photo = (id, groupId = 'g', childCode = 'A') => ({
  id,
  groupId,
  childCode,
  originalGroupId: groupId,
  revision: 1,
  code: childCode + id,
  previewSrc: '/demo.webp'
});
const photos = [photo('001'), photo('002'), photo('003', 'g', 'B'), photo('001b', 'g2')];
const rows = [{ id: 'r', groupId: 'g', code: 'A001' }];
const req = () => ({
  id: 'request',
  revision: 1,
  institutionId: 'i',
  shootId: 's',
  status: 'submitted',
  rows: requestRows(rows, groups, photos, 's', [], null).resolved
});
const cat = { revision: 1, conditionsRevision: 0, products: [{ active: true, price: 100 }] };
test('R10 fact time is Moscow, cannot be future or an impossible calendar date', () => {
  assert.equal(parseTransmission('2026-09-07T12:00', '2026-09-07T09:00:00Z'), '2026-09-07T09:00:00.000Z');
  for (const value of ['2026-09-07T12:01', '2026-02-30T12:00', '2026-01-01T25:00', ''])
    assert.equal(parseTransmission(value, '2026-09-07T09:00:00Z'), null);
});
test('R10 seven calendar days cross month/year/leap day at the same Moscow minute', () => {
  for (const [from, to] of [
    ['2026-12-29T18:12', '2027-01-05T18:12'],
    ['2028-02-25T12:00', '2028-03-03T12:00']
  ])
    assert.equal(moscowInput(calendarDays(from + ':00+03:00', 7)), to);
});
test('R10 closure boundary and legacy fact preserve previous seven-day window', () => {
  const group = { state: 'open', closesAt: '2026-09-12T18:00:00+03:00' };
  assert.equal(currentGroupState(group, '2026-09-12T15:00:00Z'), 'closed');
  assert.equal(currentGroupState(group, '2026-09-12T14:59:59Z'), 'open');
  assert.equal(groupSentAt(group), '2026-09-05T15:00:00.000Z');
  assert.equal(groupSentAt({ ...group, state: 'preparing' }), null);
});
test('R10 a frame code resolves the entire child, within the specified group only', () => {
  assert.deepEqual(
    resolveCode(photos, 'g', ' a001 ').photos.map((p) => p.id),
    ['001', '002']
  );
  assert.equal(resolveCode(photos, 'g2', 'A').photos.length, 1);
  assert.equal(resolveCode(photos, 'wrong', 'A'), null);
});
test('R10 ambiguous child/frame code rejected while legacy codes remain usable', () => {
  const list = [...photos, photo('x', 'g', 'A001')];
  assert.equal(resolveCode(list, 'g', 'A001'), null);
  assert.equal(resolveCode(list, 'g', 'A').photos.length, 2);
});
test('R10 duplicate child via different frames and other pending requests rejected', () => {
  assert.match(
    requestRows([...rows, { id: 'r2', groupId: 'g', code: 'A002' }], groups, photos, 's', [], null).errors['code:r2'],
    /уже включён/
  );
  assert.match(requestRows(rows, groups, photos, 's', [req()], null).errors['code:r'], /на проверке/);
  assert.deepEqual(requestRows(rows, groups, photos, 's', [req()], 'request').errors, {});
});
test('R10 identical codes in distinct groups are separate children; wrong shoot and staff sources rejected', () => {
  assert.equal(requestRows([...rows, { id: 'r2', groupId: 'g2', code: 'A' }], groups, photos, 's', [], null).resolved.length, 2);
  assert.ok(requestRows(rows, groups, photos, 'wrong', [], null).errors['group:r']);
  assert.ok(requestRows([{ id: 'r', groupId: 't', code: 'A' }], groups, photos, 's', [], null).errors['group:r']);
});
test('R10 preview requires a staff folder in exactly the same institution and shoot', () => {
  assert.throws(
    () =>
      reviewRequest(
        req(),
        groups.filter((g) => g.id !== 't'),
        { photos, covers: {} }
      ),
    /нет папки/
  );
  assert.throws(
    () => reviewRequest(req(), [...groups.slice(0, 2), { ...groups[2], shootId: 'other' }], { photos, covers: {} }),
    /нет папки/
  );
});
test('R10 preview allocates free child codes across the entire batch and never mutates source', () => {
  const r = req();
  r.rows.push({ id: 'r2', groupId: 'g', childCode: 'B', photoIds: ['003'] });
  const state = { photos: [...photos, photo('t1', 't', 'A')], covers: { g: '001' } };
  const before = JSON.stringify(state);
  assert.deepEqual(
    reviewRequest(r, groups, state).bundles.map((b) => b.targetCode),
    ['B', 'C']
  );
  assert.equal(JSON.stringify(state), before);
});
test('R10 additions expand full bundle and invalidate confirmation; missing submitted frames block transfer', () => {
  const r = req(),
    first = reviewRequest(r, groups, { photos, covers: {} }),
    second = reviewRequest(r, groups, { photos: [...photos, photo('004')], covers: {} });
  assert.equal(second.bundles[0].photos.length, 3);
  assert.notEqual(first.signature, second.signature);
  assert.throws(() => reviewRequest(r, groups, { photos: photos.slice(1), covers: {} }), /изменился/);
});
test('R10 existing orders are detected without mutation of snapshots', () => {
  const orders = [{ groupId: 'g', quote: { lines: [{ childCode: 'A' }] }, paid: true }];
  const before = JSON.stringify(orders);
  assert.equal(reviewRequest(req(), groups, { photos, covers: {} }, orders).hasOrders, true);
  assert.equal(JSON.stringify(orders), before);
});
test('R10 preparation responds to photos, catalog, group conditions, assignment and pending staff lists', () => {
  const g = groups[0],
    state = { photos, covers: {} };
  assert.deepEqual(preparationProblems(g, state, cat), []);
  assert.match(preparationProblems(g, { ...state, staffRequests: [req()] }, cat).join(), /проверку списков/);
  assert.match(preparationProblems(g, { photos: [], covers: {} }, cat).join(), /нет фотографий/);
  const sig = preparationSignature(g, state, cat);
  for (const next of [
    { ...cat, revision: 2 },
    { ...cat, conditionsRevision: 2 }
  ])
    assert.notEqual(preparationSignature(g, state, next), sig);
  assert.notEqual(preparationSignature({ ...g, teacherId: 7 }, state, cat), sig);
  assert.notEqual(preparationSignature(g, { ...state, photos: [...photos, photo('new')] }, cat), sig);
});

test('R10 confirmation rejects a source group moved outside the request context', () => {
  assert.throws(
    () =>
      reviewRequest(
        req(),
        groups.map((g) => (g.id === 'g' ? { ...g, institutionId: 'other' } : g)),
        { photos, covers: {} }
      ),
    /Исходная группа/
  );
});

test('F2 live link form sends an explicit Moscow moment and checks the fields first', () => {
  assert.equal(serverMoment('2026-09-22T10:15'), '2026-09-22T10:15:00+03:00');
  const base = {
    kind: 'link',
    requestId: 'r',
    groupId: 'g',
    revision: 2,
    signature: 'a'.repeat(64),
    sentAt: '2026-09-22T10:15',
    reason: '',
    confirmed: true,
    photosReviewed: true,
    conditionsReviewed: true,
    staffReviewed: true
  };
  const now = '2026-09-22T08:00:00.000Z';
  assert.deepEqual(liveLinkErrors({ ...base, action: 'prepare' }, now), {});
  assert.deepEqual(Object.keys(liveLinkErrors({ ...base, action: 'prepare', staffReviewed: false }, now)), ['staffReviewed']);
  assert.deepEqual(liveLinkErrors({ ...base, action: 'transmit' }, now), {});
  assert.deepEqual(Object.keys(liveLinkErrors({ ...base, action: 'transmit', sentAt: '2026-09-22T11:01', confirmed: false }, now)), [
    'sentAt',
    'confirmed'
  ]);
  assert.deepEqual(Object.keys(liveLinkErrors({ ...base, action: 'correct', reason: ' кра ' }, now)), ['reason']);
  assert.deepEqual(liveLinkErrors({ ...base, action: 'correct', reason: 'Ошибка в дате' }, now), {});
});

test('F2 live readiness codes are shown as sentences and demo sentences stay unchanged', () => {
  assert.equal(problemText('staffRequestsPending'), 'Сначала завершите проверку списков сотрудников этой группы.');
  assert.equal(problemText('photosProcessing'), 'Часть фотографий ещё обрабатывается.');
  assert.equal(problemText('В группе ещё нет фотографий.'), 'В группе ещё нет фотографий.');
});
test('D3 live transfer preview keeps the submitted rows and shows thumbnails only to the organizer', () => {
  const request = {
    id: 'request',
    rows: [{ id: 'row-1', groupId: 'g', code: 'A001', childCode: 'A', photoIds: ['p1'] }]
  };
  const server = {
    targetGroupId: 't',
    targetGroupName: 'Сотрудники',
    signature: 'f'.repeat(64),
    hasOrders: true,
    revision: 3,
    bundles: [
      { rowId: 'row-1', groupId: 'g', childCode: 'A', targetCode: 'C', hasOrders: true, photos: [{ id: 'p1', code: 'A001', revision: 2 }] }
    ]
  };
  const organizer = transferPreviewFromServer(server, request, true);
  assert.equal(organizer.targetGroupName, 'Сотрудники');
  assert.equal(organizer.bundles[0].row.code, 'A001');
  assert.equal(organizer.bundles[0].targetCode, 'C');
  assert.equal(organizer.bundles[0].hasOrders, true);
  assert.deepEqual(organizer.bundles[0].photos, [{ id: 'p1', code: 'A001', previewSrc: '/api/v1/photos/p1/thumb' }]);
  assert.equal(transferPreviewFromServer(server, request, false).bundles[0].photos[0].previewSrc, '');
  assert.throws(
    () => transferPreviewFromServer({ ...server, bundles: [{ ...server.bundles[0], rowId: 'gone' }] }, request, true),
    /Список изменился/
  );
});
test('D3 transfer refusals map to sentences and leave other codes to the list messages', () => {
  assert.equal(
    staffTransferErrorText('STAFF_GROUP_AMBIGUOUS'),
    'В съёмке несколько папок сотрудников. Оставьте одну и повторите проверку.'
  );
  assert.equal(staffTransferErrorText('SIGNATURE_CONFLICT'), 'Наборы изменились после проверки. Откройте проверку заново.');
  assert.equal(
    staffTransferErrorText('SHARED_PHOTO', ['B001']),
    'Кадры B001 назначены ещё и ребёнку, который остаётся в группе. Такой набор нельзя перенести.'
  );
  assert.equal(staffTransferErrorText('REVISION_CONFLICT'), null);
});
