import { demoAccounts, institutions } from '../mocks/fixtures.ts';
import { galleryChildren } from '../gallery/mocks/photos.ts';
import type { OrganizationState } from '../organization/types.js';
import type { PhotoState } from '../photos/types.js';
export const reviewNow = '2026-09-08T09:00:00.000Z';
export const reviewLinks = {
  regular: '/g/158-group-7bc93615c4e94fd18a207d560b3e1f82',
  staff: '/g/review-staff',
  other: '/g/review-school'
};
export function reviewFixture() {
  const organization: OrganizationState = {
    institutions: institutions
      .filter((i) => ['sun', 'school'].includes(i.id))
      .map((i) => ({ ...i, curatorId: i.id === 'sun' ? 102 : null, headId: i.id === 'sun' ? 103 : null, revision: 1 })),
    shoots: [
      { id: 'sun-summer-2026', institutionId: 'sun', name: 'Лето в кадре', date: '2026-09-05', revision: 1 },
      { id: 'sun-autumn-2026', institutionId: 'sun', name: 'Осенняя съёмка · 2026', date: null, revision: 1 },
      { id: 'school-summer-2026', institutionId: 'school', name: 'Лето в кадре', date: '2026-09-06', revision: 1 }
    ],
    groups: [
      {
        id: 'sun-stars',
        institutionId: 'sun',
        shootId: 'sun-summer-2026',
        shootName: 'Лето в кадре',
        name: 'Звёздочки',
        kind: 'regular',
        state: 'preparing',
        closesAt: null,
        teacherId: 104,
        galleryToken: reviewLinks.regular.slice(3),
        revision: 1
      },
      {
        id: 'sun-staff',
        institutionId: 'sun',
        shootId: 'sun-summer-2026',
        shootName: 'Лето в кадре',
        name: 'Сотрудники',
        kind: 'staff',
        state: 'preparing',
        closesAt: null,
        teacherId: null,
        galleryToken: reviewLinks.staff.slice(3),
        revision: 1
      },
      {
        id: 'sun-bees',
        institutionId: 'sun',
        shootId: 'sun-autumn-2026',
        shootName: 'Осенняя съёмка · 2026',
        name: 'Пчёлки',
        kind: 'regular',
        state: 'preparing',
        closesAt: null,
        teacherId: null,
        galleryToken: 'review-bees',
        revision: 1
      },
      {
        id: 'school-1a',
        institutionId: 'school',
        shootId: 'school-summer-2026',
        shootName: 'Лето в кадре',
        name: '1 «А» · тестовый класс',
        kind: 'regular',
        state: 'preparing',
        closesAt: null,
        teacherId: null,
        galleryToken: reviewLinks.other.slice(3),
        revision: 1
      }
    ],
    users: demoAccounts.map(({ id, name, email, role }) => ({ id, name, email, role, active: true, revision: 1, accessRevision: 1 })),
    operations: [],
    userOperations: []
  };
  const photos: PhotoState = { photos: [], covers: {}, staffRequests: [] };
  for (const group of organization.groups.filter((g) => ['sun-stars', 'school-1a'].includes(g.id))) {
    for (const child of galleryChildren[group.id] ?? [])
      for (const [index, photo] of child.photos.entries())
        photos.photos.push({
          ...photo,
          shootId: group.shootId,
          groupId: group.id,
          originalGroupId: group.id,
          childCode: child.code,
          sequence: index + 1,
          assignments: [{ childId: group.id + ':' + child.code, childCode: child.code, sequence: index + 1, code: photo.code }],
          filename: photo.code + '.webp',
          bytes: 0,
          fingerprint: 'seed:' + photo.previewSrc,
          source: 'seed',
          revision: 1
        });
    const cover = photos.photos.find((p) => p.groupId === group.id);
    if (cover) photos.covers[group.id] = cover.id;
  }
  return { organization, photos, now: reviewNow };
}
