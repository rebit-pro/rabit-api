import { baseOrder, organization, photo } from '../dashboard/fixtures.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import { settlement } from '../../../src/modules/morefoto/settlement/rules.js';
export function fixture() {
  const org = structuredClone(organization);
  org.groups[0]!.closesAt = '2026-09-07T09:00:00Z';
  org.groups[0]!.state = 'closed';
  org.groups.push({ ...org.groups[0]!, id: 'sun-staff', name: 'Сотрудники', kind: 'staff', galleryToken: 'r14-staff', teacherId: null });
  const paid = structuredClone(baseOrder);
  paid.id = 'r14-paid';
  paid.number = 'MF-R14-001';
  paid.closesAt = org.groups[0]!.closesAt;
  paid.quote.lines[1]!.product = { ...paid.quote.lines[1]!.product, id: 'pair-10x15', name: 'Пара 10×15', printCount: 2 };
  paid.quote.lines[1]!.productId = 'pair-10x15';
  const second = { ...structuredClone(paid), id: 'r14-second', number: 'MF-R14-002', accessKey: '2'.repeat(32) };
  const staff = {
    ...structuredClone(paid),
    id: 'r14-staff',
    number: 'MF-R14-003',
    groupId: 'sun-staff',
    groupName: 'Сотрудники',
    audience: 'staff' as const,
    accessKey: '3'.repeat(32)
  };
  staff.quote.lines[1]!.childCode = 'A099';
  staff.quote.lines[1]!.photoId = 'staff-photo';
  staff.quote.lines[1]!.photo = { ...photo, id: 'staff-photo', code: 'A099-01' };
  const unpaid = { ...structuredClone(paid), id: 'r14-unpaid', number: 'MF-R14-004', paymentStatus: 'pending' as const, paidAt: undefined };
  const late = { ...structuredClone(paid), id: 'r14-late', number: 'MF-R14-005', latePayment: true, accessKey: '5'.repeat(32) };
  const held = { ...structuredClone(paid), id: 'r14-held', number: 'MF-R14-006', settlement: { ...settlement(paid), hold: true } };
  const foreign = { ...structuredClone(paid), id: 'r14-foreign', groupId: 'school-1a', number: 'PRIVATE-FOREIGN' };
  const orders: OrderSnapshot[] = [paid, second, staff, unpaid, late, held, foreign];
  const photos = [1, 2].map((n) => ({
    ...photo,
    id: n === 1 ? photo.id : 'new-photo',
    code: 'A001-0' + n,
    shootId: 'sun-summer-2026',
    groupId: 'sun-stars',
    originalGroupId: 'sun-stars',
    childCode: 'A001',
    sequence: n,
    assignments: [{ childId: 'sun-stars:A001', childCode: 'A001', sequence: n, code: 'A001-0' + n }],
    filename: 'demo.webp',
    bytes: 0,
    fingerprint: 'r14-' + n,
    source: 'seed',
    revision: 1
  }));
  photos.push({
    ...photos[0]!,
    id: 'staff-photo',
    code: 'A099-01',
    groupId: 'sun-staff',
    childCode: 'A099',
    sequence: 1,
    assignments: [{ childId: 'sun-staff:A099', childCode: 'A099', sequence: 1, code: 'A099-01' }]
  });
  const requests = [
    {
      id: 'staff-list',
      institutionId: 'sun',
      shootId: 'sun-summer-2026',
      createdBy: 104,
      createdAt: '2026-09-05T09:00:00Z',
      revision: 1,
      status: 'transferred',
      rows: [],
      comment: '',
      history: [],
      results: [
        {
          rowId: 'r',
          fromGroupId: 'sun-stars',
          fromChildCode: 'A001',
          targetGroupId: 'sun-staff',
          targetChildCode: 'A099',
          photoIds: ['staff-photo']
        }
      ]
    }
  ];
  return { org, orders, photos: { photos, covers: {}, staffRequests: requests } };
}
