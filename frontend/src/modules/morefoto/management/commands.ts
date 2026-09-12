import type { Catalog, Product } from '../commerce/types';
import type { OrganizationState } from '../organization/types';
import type { ConditionsCommand, ProductCommand, UserCommand } from './types';
import { assignmentSignature } from './rules';
export function editProduct(catalog: Catalog, product?: Product): ProductCommand {
  const value = product ?? {
    id: 'product-' + crypto.randomUUID(),
    name: '',
    description: '',
    kind: 'physical',
    price: 0,
    printCount: 1,
    staffDiscount: false,
    active: true
  };
  return {
    kind: 'product',
    requestId: crypto.randomUUID(),
    revision: catalog.revision,
    product: {
      ...value,
      price: String(value.price / 100),
      printCount: String(value.printCount),
      format: value.format ?? (value.kind === 'physical' ? (value.name.match(/\d+\s*×\s*\d+/)?.[0] ?? 'По описанию') : 'Электронный файл'),
      unit:
        value.unit ?? (value.kind === 'bundle' ? 'комплект' : value.kind === 'digital' ? 'файл' : value.printCount > 1 ? 'комплект' : 'шт.')
    }
  };
}
export function editConditions(
  catalog: Catalog,
  effective: Catalog,
  state: OrganizationState,
  groupId: string | null,
  inherit: boolean
): ConditionsCommand {
  const group = state.groups.find((x) => x.id === groupId);
  return {
    kind: 'conditions',
    requestId: crypto.randomUUID(),
    revision: catalog.revision,
    conditionsRevision: effective.conditionsRevision ?? 0,
    groupId,
    shootId: group?.shootId ?? null,
    institutionId: group?.institutionId ?? null,
    inherit,
    products: effective.products.map((p) => ({
      id: p.id,
      name: p.name,
      kind: p.kind,
      price: String(p.price / 100),
      active: p.active,
      staffDiscount: p.staffDiscount
    })),
    giftEnabled: effective.giftThreshold > 0,
    giftThreshold: String(effective.giftThreshold / 100),
    giftForStaff: effective.giftForStaff
  };
}
export function editUser(state: OrganizationState, id: number | null): UserCommand {
  const user = state.users.find((x) => x.id === id);
  return {
    kind: 'user',
    requestId: crypto.randomUUID(),
    id,
    revision: user?.revision ?? null,
    assignmentSignature: assignmentSignature(state),
    name: user?.name ?? '',
    email: user?.email ?? '',
    role: user?.role ?? 'teacher',
    active: user?.active ?? true,
    replaceAssignments: false,
    institutionIds: state.institutions
      .filter((x) => (user?.role === 'curator' && x.curatorId === id) || (user?.role === 'head' && x.headId === id))
      .map((x) => x.id),
    groupIds: user?.role === 'teacher' ? state.groups.filter((x) => x.teacherId === id).map((x) => x.id) : []
  };
}
