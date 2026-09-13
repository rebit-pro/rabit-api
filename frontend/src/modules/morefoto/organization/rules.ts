import type { EntityFields, FieldErrors, OrganizationState, SaveCommand, StaffOption } from './types';

export const blankFields = (): EntityFields => ({
  name: '',
  address: '',
  date: '',
  curatorId: null,
  headId: null,
  teacherId: null,
  groupKind: 'regular'
});
const normalized = (value: string) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('ru');
export function validShootDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value < '2000-01-01' || value > '2100-12-31') return false;
  const date = new Date(value + 'T12:00:00Z');
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}
export function validateEntity(command: SaveCommand, state: OrganizationState, staff: StaffOption[]): FieldErrors {
  const { fields, kind, id, parentId } = command;
  const errors: FieldErrors = {};
  const name = normalized(fields.name);
  if (name.length < 2 || name.length > 120) errors.name = 'Введите название от 2 до 120 символов.';
  function assignment(field: 'curatorId' | 'headId' | 'teacherId', role: string) {
    const value = fields[field];
    if (value !== null && !staff.some((item) => item.id === value && item.role === role))
      errors[field] = 'Выберите сотрудника с подходящей ролью.';
  }
  if (kind === 'institution') {
    const address = normalized(fields.address);
    if (address.length < 5 || address.length > 240) errors.address = 'Введите адрес от 5 до 240 символов.';
    if (state.institutions.some((item) => item.id !== id && normalized(item.name) === name && normalized(item.address) === address)) {
      errors.name = 'Учреждение с таким названием и адресом уже существует.';
    }
    assignment('curatorId', 'curator');
    assignment('headId', 'head');
  } else if (kind === 'shoot') {
    if (!validShootDate(fields.date)) errors.date = 'Укажите существующую дату съёмки (2000–2100).';
    if (
      state.shoots.some(
        (item) => item.id !== id && item.institutionId === parentId && normalized(item.name) === name && item.date === fields.date
      )
    ) {
      errors.name = 'Съёмка с таким названием и датой уже существует в учреждении.';
    }
  } else {
    if (!['regular', 'staff'].includes(fields.groupKind)) errors.groupKind = 'Выберите тип группы.';
    if (state.groups.some((item) => item.id !== id && item.shootId === parentId && normalized(item.name) === name)) {
      errors.name = 'Группа с таким названием уже есть в этой съёмке.';
    }
    if (fields.groupKind === 'staff' && state.groups.some((item) => item.id !== id && item.shootId === parentId && item.kind === 'staff')) {
      errors.groupKind = 'В этой съёмке уже есть папка сотрудников.';
    }
    assignment('teacherId', 'teacher');
  }
  return errors;
}
export function scopedOrganization(state: OrganizationState, account: StaffOption, now?: string) {
  const institutions = state.institutions.filter(
    (item) =>
      account.role === 'organizer' ||
      (account.role === 'curator' && item.curatorId === account.id) ||
      (account.role === 'head' && item.headId === account.id) ||
      (account.role === 'teacher' && state.groups.some((group) => group.institutionId === item.id && group.teacherId === account.id))
  );
  const allowed = new Set(institutions.map((item) => item.id));
  const groups = state.groups.filter(
    (item) => allowed.has(item.institutionId) && (account.role !== 'teacher' || item.teacherId === account.id)
  );
  const shoots = state.shoots.filter(
    (item) => allowed.has(item.institutionId) && (account.role !== 'teacher' || groups.some((group) => group.shootId === item.id))
  );
  return {
    institutions: institutions.map(({ id, name, address }) => ({ id, name, address })),
    groups: groups.map(({ id, institutionId, name, shootName, kind, state, closesAt, shootId, galleryToken }) => ({
      id,
      institutionId,
      name,
      shootName,
      kind,
      state: now && state === 'open' && closesAt && Date.parse(closesAt) <= Date.parse(now) ? ('closed' as const) : state,
      closesAt,
      shootId,
      galleryToken
    })),
    shoots
  };
}
