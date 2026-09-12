import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
const token = '158-group-7bc93615c4e94fd18a207d560b3e1f82';
const photo = {
  id: 'sun-stars-A001-1',
  code: 'A001-01',
  thumbSrc: '/demo/gallery-v1/sun-stars/0f21215dfee629dee643-thumb.webp',
  previewSrc: '/demo/gallery-v1/sun-stars/0f21215dfee629dee643-preview.webp',
  width: 1200,
  height: 1800
};
const product = {
  id: 'digital',
  name: 'Электронный кадр',
  description: 'Один файл выбранного снимка. Повторное скачивание в период доступа бесплатно.',
  kind: 'digital' as const,
  price: 25000,
  printCount: 0,
  staffDiscount: true,
  active: true
};
export const baseOrder: OrderSnapshot = {
  id: 'r13-paid',
  accessKey: '1'.repeat(32),
  requestId: 'a'.repeat(32),
  number: 'MF-R13-001',
  groupId: 'sun-stars',
  galleryToken: token,
  institutionName: 'Детский сад «Солнечный»',
  groupName: 'Звёздочки',
  shootName: 'Лето в кадре',
  audience: 'regular',
  createdAt: '2026-09-05T09:00:00Z',
  closesAt: '2026-09-12T15:00:00Z',
  buyer: {
    name: 'Покупатель R13',
    phone: '+7 (900) 111-22-33',
    email: 'buyer-r13@example.test',
    comment: 'Комментарий покупателя',
    receiptChannel: 'email'
  },
  quote: {
    lines: [
      {
        id: 'line-1',
        childCode: 'A001',
        photoId: photo.id,
        productId: 'digital',
        quantity: 1,
        product,
        photo,
        unitPrice: 25000,
        total: 25000,
        discount: 0,
        coveredByGift: false
      }
    ],
    total: 25000,
    subtotal: 25000,
    discount: 0,
    giftSaving: 0,
    gifts: [],
    count: 1,
    invalid: [],
    revision: 1
  },
  digitalPhotos: [photo],
  paymentStatus: 'paid',
  paidAt: '2026-09-05T09:10:00Z',
  paymentAttempts: [
    {
      id: 'attempt-1',
      requestId: 'b'.repeat(32),
      amount: 25000,
      status: 'paid',
      startedAt: '2026-09-05T09:00:00Z',
      completedAt: '2026-09-05T09:10:00Z'
    }
  ],
  productionStatus: 'not-started',
  supportRequests: []
};
baseOrder.quote.lines.push({
  ...baseOrder.quote.lines[0]!,
  id: 'line-2',
  productId: 'print',
  product: { ...product, id: 'print', name: 'Фото 15×21', kind: 'physical', price: 10000, printCount: 1 },
  quantity: 2,
  unitPrice: 10000,
  total: 20000
});
baseOrder.quote.total = 45000;
baseOrder.quote.subtotal = 45000;
baseOrder.quote.count = 3;
baseOrder.paymentAttempts![0]!.amount = 45000;
export const organization: Pick<OrganizationState, 'institutions' | 'shoots' | 'groups' | 'operations'> = {
  institutions: [
    { id: 'sun', name: 'Детский сад «Солнечный»', address: 'Учебная улица, 12', curatorId: 102, headId: 103, revision: 1 },
    { id: 'school', name: 'Чужое учреждение', address: 'Учебная улица, 45', curatorId: null, headId: null, revision: 1 }
  ],
  shoots: [
    { id: 'sun-summer-2026', institutionId: 'sun', name: 'Лето в кадре', date: '2026-09-05', revision: 1 },
    { id: 'school-summer-2026', institutionId: 'school', name: 'Другая съёмка', date: null, revision: 1 }
  ],
  groups: [
    {
      id: 'sun-stars',
      institutionId: 'sun',
      shootId: 'sun-summer-2026',
      shootName: 'Лето в кадре',
      name: 'Звёздочки',
      kind: 'regular',
      state: 'open',
      closesAt: '2026-09-12T15:00:00Z',
      teacherId: 104,
      galleryToken: token,
      revision: 1
    },
    {
      id: 'school-1a',
      institutionId: 'school',
      shootId: 'school-summer-2026',
      shootName: 'Другая съёмка',
      name: 'Чужой класс',
      kind: 'regular',
      state: 'open',
      closesAt: '2026-09-12T15:00:00Z',
      teacherId: null,
      galleryToken: 'other-r13',
      revision: 1
    }
  ],
  operations: []
};

export { photo };
