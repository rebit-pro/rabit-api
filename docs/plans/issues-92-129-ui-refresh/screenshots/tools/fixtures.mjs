// Stub data for live-mode cabinet screenshots: shapes follow frontend/src/modules/morefoto/structure/model.ts and orders/live/types.ts.
const now = Date.parse('2026-10-13T09:00:00Z');
const iso = (days) => new Date(now + days * 86400000).toISOString().replace('.000Z', '+00:00');
export const me = {
  id: 7, email: 'organizer@morefoto36.ru', name: 'Александр Тарасов', role: 'organizer', active: true, accessRevision: 1,
  permissions: ['organization.manage', 'order.read', 'catalog.manage', 'staff.manage', 'media.manage', 'link.manage', 'order.manage'],
  avatar: null, support: null
};
const curators = ['Маргарита Попова', 'Алёна Смирнова', null, 'Маргарита Попова', 'Ирина Волкова', 'Алёна Смирнова', 'Ирина Волкова'];
const names = [
  ['Детский сад «Солнышко»', 'Воронеж, ул. Лизюкова, 12'],
  ['Детский сад №45 «Радуга»', 'Воронеж, Московский пр-т, 98'],
  ['Школа №29', 'Воронеж, ул. Хользунова, 7'],
  ['Детский сад «Звёздочка»', 'Воронеж, ул. Кольцовская, 33'],
  ['Гимназия им. Кольцова', 'Воронеж, пр-т Революции, 18'],
  ['Детский сад «Берёзка»', 'Нововоронеж, ул. Ленина, 4'],
  ['Центр развития «Умка»', 'Воронеж, ул. Ленинградская, 120']
];
export const institutions = names.map(([name, address], i) => ({
  id: 'inst-' + (i + 1), name, address, revision: 1, curatorId: curators[i] ? 10 + i : null, headId: null,
  curatorName: curators[i], headName: null, shootCount: [3, 2, 4, 1, 2, 1, 0][i], groupCount: [9, 6, 14, 3, 7, 2, 0][i],
  openGroupCount: [4, 0, 6, 1, 2, 0, 0][i]
}));
const shootNames = ['Осенняя съёмка', 'Выпускной 2026', 'Портреты на фоне', 'Новогодняя сказка'];
export const shoots = [];
institutions.forEach((inst, i) => {
  for (let s = 0; s < inst.shootCount; s++) {
    shoots.push({ id: `shoot-${i + 1}-${s + 1}`, institutionId: inst.id, institutionName: inst.name, name: shootNames[(i + s) % shootNames.length],
      date: new Date(now - (s * 21 + i * 3) * 86400000).toISOString().slice(0, 10), revision: 1,
      groupCount: Math.max(1, Math.round(inst.groupCount / Math.max(1, inst.shootCount))), openGroupCount: s === 0 ? Math.min(inst.openGroupCount, 3) : 0 });
  }
});
const groupNames = ['Звёздочки', 'Солнышки', 'Почемучки', 'Капельки', 'Гномики', 'Пчёлки', 'Сотрудники', 'Ромашки'];
const states = ['open', 'open', 'digital', 'preparing', 'closed', 'open', 'preparing', 'open'];
export function groupsOf(shootId) {
  return groupNames.map((name, g) => {
    const status = states[g];
    const sent = status === 'preparing' ? null : status === 'open' ? -1 - (g % 4) : status === 'digital' ? -9 : -20;
    return { id: `${shootId}-g${g + 1}`, shootId, name, groupKind: name === 'Сотрудники' ? 'staff' : 'regular', revision: 1,
      teacherId: g % 3 === 0 ? null : 20 + g, status, timezone: 'Europe/Moscow', sentAt: sent === null ? null : iso(sent),
      closesAt: sent === null ? null : iso(sent + 7), digitalSalesUntil: sent === null ? null : iso(sent + 14), deliveryDueAt: sent === null ? null : iso(sent + 14) };
  });
}
export function shootDetail(id) {
  const shoot = shoots.find((s) => s.id === id) ?? shoots[0];
  const items = groupsOf(shoot.id);
  const byState = { preparing: 0, open: 0, digital: 0, closed: 0 };
  items.forEach((g) => byState[g.status]++);
  return { ...shoot, assignmentSignature: 'sig', groups: { items, meta: { page: 1, pageSize: 25, total: items.length, totalPages: 1, summary: { byState } } } };
}
export function institutionDetail(id) {
  const inst = institutions.find((i) => i.id === id) ?? institutions[0];
  const own = shoots.filter((s) => s.institutionId === inst.id);
  const groups = own.flatMap((s) => groupsOf(s.id).slice(0, 4).map((g) => ({ ...g, shootName: s.name })));
  const byState = { preparing: 0, open: 0, digital: 0, closed: 0 };
  groups.forEach((g) => byState[g.status]++);
  return { ...inst, shoots: { items: own, meta: { page: 1, pageSize: 25, total: own.length, totalPages: 1 } },
    groups: { items: groups, meta: { page: 1, pageSize: 25, total: groups.length, totalPages: 1, summary: { byState } } },
    summary: { availability: 'unavailable', reason: 'dependenciesNotReady' }, assignmentSignature: 'sig' };
}
const emails = ['anna.k@mail.ru', 'petrov.d@yandex.ru', 'olga1985@gmail.com', 'smirnova.e@mail.ru', 'mama.vani@yandex.ru', 'k.belova@inbox.ru', 'ivanova.t@mail.ru', 'sergey.m@gmail.com', 'lena-v@yandex.ru', 'nikolaeva@mail.ru'];
const pay = ['paid', 'paid', 'unpaid', 'paid', 'pending', 'paid', 'declined', 'paid', 'unpaid', 'paid'];
const prod = ['not-started', 'printing', 'not-started', 'ready', 'not-started', 'delivered', 'not-started', 'queued', 'not-started', 'ready'];
export const orders = emails.map((email, i) => {
  const inst = institutions[i % 4];
  return { id: 'ord-' + (i + 1), number: 'MF-26-' + String(1041 - i).padStart(5, '0'), institutionId: inst.id, institutionName: inst.name,
    shootId: 'shoot-1-1', shootName: 'Осенняя съёмка', groupId: 'g' + i, groupName: groupNames[i % 6], audience: 'regular', createdAt: iso(-i * 0.4),
    buyer: { name: null, phone: null, email, comment: '', receiptChannel: 'email' },
    quote: { lines: [], total: [1260, 540, 2380, 180, 900, 3150, 360, 720, 1800, 450][i] * 100, subtotal: 0, discount: 0, giftSaving: 0, gifts: [], count: 2, invalid: [], revision: 1, conditionsRevision: 1 },
    paymentStatus: pay[i], paidAt: pay[i] === 'paid' ? iso(-i * 0.4) : null, latePayment: false, productionStatus: prod[i], version: '1' };
});
