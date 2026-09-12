import { isMockApiEnabled } from '@/mocks/config';
import { DemoError, requireDemoAccount } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { readOrganization, getStaffOptions, writeOrganization } from './repository';
import { validateEntity } from './rules';
import type { FieldErrors, OrganizationSnapshot, SaveCommand, SaveResult } from './types';

export class OrganizationValidationError extends Error {
  constructor(public errors: FieldErrors) {
    super('Проверьте выделенные поля.');
  }
}
function organizer(token: string) {
  if (!isMockApiEnabled) throw new DemoError(403, 'Изменение учреждений пока недоступно.');
  const account = requireDemoAccount(token);
  if (account.role !== 'organizer') throw new DemoError(403, 'Изменять учреждения и съёмки может только организатор.');
  return account;
}
export async function loadOrganization(token: string): Promise<OrganizationSnapshot> {
  await simulateRequest();
  organizer(token);
  return { ...readOrganization(), staff: structuredClone(getStaffOptions()) };
}
export async function saveEntity(token: string, command: SaveCommand): Promise<SaveResult> {
  organizer(token);
  await simulateRequest();
  const commit = () => {
    const account = organizer(token);
    const state = readOrganization();
    const completed = state.operations.find((item) => item.requestId === command.requestId && item.actorId === account.id);
    if (completed) return { kind: completed.kind, id: completed.id };
    if (!command.requestId || !['institution', 'shoot', 'group'].includes(command.kind)) throw new DemoError(400, 'Некорректный запрос.');
    const collection = command.kind === 'institution' ? state.institutions : command.kind === 'shoot' ? state.shoots : state.groups;
    const existing = command.id ? collection.find((item) => item.id === command.id) : undefined;
    if (command.id && !existing) throw new DemoError(404, 'Запись не найдена. Откройте список заново.');
    if (existing && existing.revision !== command.revision)
      throw new DemoError(
        409,
        'Запись изменена в другой вкладке. Черновик сохранён. Закройте форму и откройте её заново, затем выберите «Загрузить актуальные данные».'
      );
    const institution = state.institutions.find((item) => item.id === command.parentId);
    const shoot = state.shoots.find((item) => item.id === command.parentId);
    if (
      command.kind === 'shoot' &&
      (!institution || (existing && 'institutionId' in existing && existing.institutionId !== institution.id))
    )
      throw new DemoError(400, 'Учреждение съёмки недоступно.');
    if (command.kind === 'group' && (!shoot || (existing && 'shootId' in existing && existing.shootId !== shoot.id)))
      throw new DemoError(400, 'Съёмка группы недоступна.');
    if (command.kind === 'group' && existing && 'kind' in existing && existing.kind !== command.fields.groupKind)
      throw new DemoError(400, 'Тип существующей группы нельзя изменить.');
    const errors = validateEntity(command, state, getStaffOptions());
    if (Object.keys(errors).length) throw new OrganizationValidationError(errors);
    const id = existing?.id ?? command.kind + '-' + crypto.randomUUID();
    const revision = (existing?.revision ?? 0) + 1;
    const name = command.fields.name.trim().replace(/\s+/g, ' ');
    if (command.kind === 'institution') {
      const value = {
        id,
        name,
        address: command.fields.address.trim(),
        curatorId: command.fields.curatorId,
        headId: command.fields.headId,
        revision
      };
      if (existing) state.institutions = state.institutions.map((item) => (item.id === id ? value : item));
      else state.institutions.push(value);
    } else if (command.kind === 'shoot' && institution) {
      const value = { id, name, institutionId: institution.id, date: command.fields.date, revision };
      if (existing) state.shoots = state.shoots.map((item) => (item.id === id ? value : item));
      else state.shoots.push(value);
      state.groups = state.groups.map((item) => (item.shootId === id ? { ...item, shootName: name } : item));
    } else if (command.kind === 'group' && shoot) {
      const previous = state.groups.find((item) => item.id === id);
      const value = {
        ...previous,
        id,
        name,
        institutionId: shoot.institutionId,
        shootId: shoot.id,
        shootName: shoot.name,
        kind: command.fields.groupKind,
        teacherId: command.fields.teacherId,
        revision,
        state: previous?.state ?? ('preparing' as const),
        closesAt: previous?.closesAt ?? null,
        galleryToken: previous?.galleryToken ?? crypto.randomUUID()
      };
      if (previous) state.groups = state.groups.map((item) => (item.id === id ? value : item));
      else state.groups.push(value);
    }
    state.operations.push({ requestId: command.requestId, actorId: account.id, kind: command.kind, id });
    writeOrganization(state);
    return { kind: command.kind, id };
  };
  return navigator.locks ? navigator.locks.request('morefoto:organization:write', commit) : commit();
}
