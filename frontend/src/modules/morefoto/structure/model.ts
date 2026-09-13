export type StructureKind = 'institution' | 'shoot' | 'group';
export interface Institution {
  id: string;
  name: string;
  address: string;
  revision: number;
  curatorId: number | null;
  headId: number | null;
}
export interface Shoot {
  id: string;
  institutionId: string;
  name: string;
  date: string | null;
  revision: number;
}
export interface Group {
  id: string;
  shootId: string;
  name: string;
  groupKind: 'regular' | 'staff';
  revision: number;
  teacherId: number | null;
  status: 'preparing' | 'open' | 'closed';
  timezone: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryDueAt: string | null;
}
export type StructureItem = Institution | Shoot | Group;
export interface PageMeta {
  page: number;
  pageSize: number;
  total: number;
  totalPages: number;
}
export interface StructureScope {
  kind: StructureKind;
  institutionId?: string;
  shootId?: string;
}
export interface ShootDetail extends Shoot {
  assignmentSignature: string;
  groups: { items: Group[]; meta: PageMeta };
}
export interface StructurePage {
  items: StructureItem[];
  meta: PageMeta;
  shoot?: ShootDetail;
}
export interface StructureFields {
  name: string;
  address: string;
  date: string;
  groupKind: 'regular' | 'staff';
}
export type FieldErrors = Partial<Record<keyof StructureFields, string>>;
export interface StructureAttempt {
  method: 'POST' | 'PATCH';
  path: string;
  key: string;
  body: {
    name: string;
    address?: string;
    date?: string | null;
    groupKind?: 'regular' | 'staff';
    revision?: number;
  };
}
export interface StructureDraft {
  kind: StructureKind;
  parentId: string | null;
  id: string | null;
  revision: number | null;
  fields: StructureFields;
  base: StructureFields;
  key: string;
  pending: StructureAttempt | null;
}
export function fieldsFrom(item?: StructureItem): StructureFields {
  return {
    name: item?.name ?? '',
    address: item && 'address' in item ? item.address : '',
    date: item && 'date' in item ? (item.date ?? '') : '',
    groupKind: item && 'groupKind' in item ? item.groupKind : 'regular'
  };
}
export function validateFields(kind: StructureKind, fields: StructureFields): FieldErrors {
  const errors: FieldErrors = {};
  if (
    !fields.name.trim() ||
    [...fields.name].length > 255 ||
    [...fields.name].some((character) => character.charCodeAt(0) < 32 || character.charCodeAt(0) === 127)
  )
    errors.name = 'Название: от 1 до 255 символов.';
  if (kind === 'institution' && [...fields.address].length > 500) errors.address = 'Адрес: до 500 символов.';
  if (kind === 'shoot' && fields.date) {
    const date = new Date(fields.date + 'T00:00:00Z');
    if (
      !/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(fields.date) ||
      Number.isNaN(date.getTime()) ||
      date.toISOString().slice(0, 10) !== fields.date
    )
      errors.date = 'Укажите существующую дату или оставьте поле пустым.';
  }
  return errors;
}
export function createAttempt(draft: StructureDraft): StructureAttempt {
  const { kind, id, parentId, fields, base } = draft;
  const collection =
    kind === 'institution'
      ? '/institutions'
      : kind === 'shoot'
        ? '/institutions/' + encodeURIComponent(parentId ?? '') + '/shoots'
        : '/shoots/' + encodeURIComponent(parentId ?? '') + '/groups';
  const resource = kind === 'institution' ? '/institutions/' : kind === 'shoot' ? '/shoots/' : '/groups/';
  const body: StructureAttempt['body'] = { name: fields.name.trim() };
  if (id) body.revision = draft.revision ?? 0;
  if (kind === 'institution' && (!id || fields.address !== base.address)) body.address = fields.address.trim();
  if (kind === 'shoot' && (!id || fields.date !== base.date)) body.date = fields.date || null;
  if (kind === 'group' && !id) body.groupKind = fields.groupKind;
  return {
    method: id ? 'PATCH' : 'POST',
    path: '/api/v1' + (id ? resource + encodeURIComponent(id) : collection),
    key: draft.key,
    body
  };
}
/** Explicit reload replaces the editor with the current server fields and revision. */
export function refreshDraft(draft: StructureDraft, item: StructureItem, key: string): StructureDraft {
  const fields = fieldsFrom(item);
  return {
    ...draft,
    revision: item.revision,
    fields,
    base: { ...fields },
    key,
    pending: null
  };
}
