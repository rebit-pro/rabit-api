import { demoAccounts, groups, institutions } from '../mocks/fixtures';
import { galleryLinks } from '../gallery/links';
import { readDemo, writeDemo } from '../mocks/storage';
import type { OrganizationState, PhotoShoot, StaffOption } from './types';

export const organizationKey = 'organization:v1';
export const organizationChangedEvent = 'morefoto:organization:changed';
const seedUsers = () =>
  demoAccounts.map(({ id, name, email, role }) => ({ id, name, email, role, active: true, revision: 1, accessRevision: 1 }));
export function getStaffOptions(): StaffOption[] {
  return readOrganization().users.filter((user) => user.active);
}
const seedShoots: PhotoShoot[] = [
  { id: 'school-summer-2026', institutionId: 'school', name: 'Лето в кадре', date: null, revision: 1 },
  { id: 'sun-summer-2026', institutionId: 'sun', name: 'Лето в кадре', date: null, revision: 1 },
  { id: 'sun-autumn-2026', institutionId: 'sun', name: 'Осенняя съёмка · 2026', date: null, revision: 1 },
  { id: 'rainbow-autumn-2026', institutionId: 'rainbow', name: 'Осенняя съёмка · 2026', date: null, revision: 1 }
];
function seedOrganization(): OrganizationState {
  return {
    institutions: institutions.map((item) => ({
      ...item,
      curatorId: item.id === 'sun' ? 102 : null,
      headId: item.id === 'sun' ? 103 : null,
      revision: 1
    })),
    shoots: structuredClone(seedShoots),
    groups: groups.map((item) => ({
      ...item,
      shootId: seedShoots.find((shoot) => shoot.institutionId === item.institutionId && shoot.name === item.shootName)!.id,
      teacherId: item.id === 'sun-stars' ? 104 : null,
      galleryToken: galleryLinks[item.id] ?? 'preparing-' + item.id,
      revision: 1
    })),
    users: seedUsers(),
    userOperations: [],
    operations: []
  };
}
export function readOrganization(): OrganizationState {
  const state = readDemo<OrganizationState>(organizationKey, seedOrganization());
  return { ...state, users: state.users ?? seedUsers(), userOperations: state.userOperations ?? [] };
}
export function writeOrganization(state: OrganizationState): void {
  writeDemo(organizationKey, state);
  window.dispatchEvent(new Event(organizationChangedEvent));
}
