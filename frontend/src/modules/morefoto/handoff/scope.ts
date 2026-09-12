import { isMockApiEnabled } from '@/mocks/config';
import { DemoError, requireDemoAccount } from '../mocks/service';
import { readOrganization } from '../organization/repository';
import { scopedOrganization } from '../organization/rules';
import { getDemoNow } from '../mocks/clock';
import type { StaffRequest } from './types';
export function handoffAccess(token: string) {
  if (!isMockApiEnabled) throw new DemoError(403, 'Действие доступно только в демонстрации.');
  const account = requireDemoAccount(token),
    organization = readOrganization();
  const scope = scopedOrganization(organization, account);
  return { account, organization, scope, now: getDemoNow() };
}
export function requireGroup(token: string, groupId: string) {
  const access = handoffAccess(token);
  const group = access.organization.groups.find((g) => g.id === groupId && access.scope.groups.some((item) => item.id === g.id));
  if (!group) throw new DemoError(403, 'Группа недоступна в вашей области.');
  return { ...access, group };
}
export function canReadRequest(request: StaffRequest, access: ReturnType<typeof handoffAccess>): boolean {
  if (access.account.role === 'organizer') return true;
  if (access.account.role === 'curator') return access.scope.institutions.some((i) => i.id === request.institutionId);
  return (
    access.account.role === 'teacher' &&
    request.createdBy === access.account.id &&
    request.rows.every((row) => access.scope.groups.some((g) => g.id === row.groupId))
  );
}
export function requireReview(token: string, request: StaffRequest) {
  const access = handoffAccess(token);
  if (!['organizer', 'curator'].includes(access.account.role) || !canReadRequest(request, access))
    throw new DemoError(403, 'Проверка доступна организатору или куратору этого учреждения.');
  return access;
}
export async function handoffLock<T>(operation: () => T | Promise<T>): Promise<T> {
  if (!navigator.locks) return operation();
  return navigator.locks.request('morefoto:organization:write', () => navigator.locks.request('morefoto:photos:write', operation));
}
export class HandoffValidationError extends Error {
  constructor(public errors: Record<string, string>) {
    super('Проверьте выделенные поля.');
  }
}
export function conflict(): never {
  throw new DemoError(409, 'Данные изменились. Загрузите актуальные данные и проверьте результат ещё раз.');
}
