import type { Catalog, Product } from '../commerce/types.js';
import type { OrganizationState } from '../organization/types.js';
import type { ConditionsCommand, ProductCommand, UserCommand, ManagementErrors, ManagedStaff } from './types.js';
import { moneyInputValue, quantityValue } from '../ui/field-values.ts';

export function checkedPrice(value: string): number | null {
  const parsed = moneyInputValue(value);
  return parsed !== null && parsed <= 100000000 ? parsed : null;
}
export function productErrors(command: ProductCommand, catalog: Catalog): ManagementErrors {
  const p = command.product,
    errors: ManagementErrors = {};
  if (!p.name.trim() || p.name.trim().length > 100) errors.name = 'Название: от 1 до 100 символов.';
  if (catalog.products.some((x) => x.id !== p.id && x.name.trim().toLowerCase() === p.name.trim().toLowerCase()))
    errors.name = 'Такое название уже есть.';
  if (!['physical', 'digital', 'bundle'].includes(p.kind)) errors.kind = 'Выберите тип продукции.';
  const existing = catalog.products.find((x) => x.id === p.id);
  if (existing && existing.kind !== p.kind) errors.kind = 'Тип существующей продукции нельзя изменить.';
  if (p.kind !== 'physical' && catalog.products.some((x) => x.id !== p.id && x.kind === p.kind))
    errors.kind = 'Электронный кадр и комплект уже есть в каталоге. Измените существующую позицию.';
  if (checkedPrice(p.price) === null) errors.price = 'Введите цену от 0 до 1 000 000 ₽, не более двух знаков после запятой.';
  if (!p.format.trim() || p.format.length > 80) errors.format = 'Укажите формат, до 80 символов.';
  if (!p.unit.trim() || p.unit.length > 40) errors.unit = 'Укажите единицу продажи, до 40 символов.';
  if (!p.description.trim() || p.description.length > 600) errors.description = 'Описание: от 1 до 600 символов.';
  if (p.kind === 'physical' && quantityValue(p.printCount, 1, 99) === null)
    errors.printCount = 'Количество отпечатков в единице: от 1 до 99.';
  return errors;
}
export function productValue(command: ProductCommand): Product {
  return {
    ...command.product,
    name: command.product.name.trim(),
    format: command.product.format.trim(),
    unit: command.product.unit.trim(),
    description: command.product.description.trim(),
    price: checkedPrice(command.product.price)!,
    printCount: command.product.kind === 'physical' ? Number(command.product.printCount) : 0
  };
}
export function conditionsErrors(command: ConditionsCommand, catalog: Catalog): ManagementErrors {
  const errors: ManagementErrors = {};
  if (command.groupId && command.inherit) return errors;
  const ids = command.products.map((x) => x.id);
  if (new Set(ids).size !== ids.length || ids.length !== catalog.products.length || catalog.products.some((x) => !ids.includes(x.id)))
    errors.products = 'Ассортимент изменился. Загрузите актуальные данные.';
  for (const p of command.products)
    if (checkedPrice(p.price) === null) errors['price:' + p.id] = 'Цена: 0–1 000 000 ₽, до двух знаков после запятой.';
  if (command.giftEnabled && (checkedPrice(command.giftThreshold) === null || checkedPrice(command.giftThreshold) === 0))
    errors.giftThreshold = 'Для подарка укажите порог больше 0 и до 1 000 000 ₽.';
  return errors;
}
export function assignmentSignature(state: OrganizationState): string {
  return JSON.stringify([
    state.institutions.map((x) => [x.id, x.curatorId, x.headId]),
    state.groups.map((x) => [x.id, x.shootId, x.teacherId])
  ]);
}
export function replacementNames(command: UserCommand, state: OrganizationState): string[] {
  if (!command.active) return [];
  const ids =
    command.role === 'teacher'
      ? state.groups
          .filter((x) => command.groupIds.includes(x.id) && x.teacherId !== null && x.teacherId !== command.id)
          .map((x) => x.teacherId)
      : command.role === 'curator' || command.role === 'head'
        ? state.institutions
            .filter((x) => command.institutionIds.includes(x.id))
            .map((x) => (command.role === 'curator' ? x.curatorId : x.headId))
            .filter((x) => x !== null && x !== command.id)
        : [];
  return [...new Set(ids)].map((id) => state.users.find((x) => x.id === id)?.name ?? 'Сотрудник');
}
export function userErrors(command: UserCommand, state: OrganizationState, actorId: number): ManagementErrors {
  const e: ManagementErrors = {};
  if (!command.name.trim() || command.name.trim().length > 100) e.name = 'Имя: от 1 до 100 символов.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(command.email.trim()) || command.email.length > 254) e.email = 'Введите корректный email.';
  if (state.users.some((x) => x.id !== command.id && x.email.toLowerCase() === command.email.trim().toLowerCase()))
    e.email = 'Этот email уже используется.';
  if (!['organizer', 'curator', 'head', 'teacher'].includes(command.role)) e.role = 'Выберите роль.';
  if (command.id === actorId && (command.role !== 'organizer' || !command.active)) e.role = 'Нельзя отключить свой доступ организатора.';
  const previous = state.users.find((x) => x.id === command.id);
  if (
    previous?.role === 'organizer' &&
    previous.active &&
    (!command.active || command.role !== 'organizer') &&
    !state.users.some((x) => x.id !== command.id && x.active && x.role === 'organizer')
  )
    e.role = 'Нужен хотя бы один активный организатор.';
  if (command.institutionIds.some((id) => !state.institutions.some((x) => x.id === id))) e.scope = 'Учреждение больше недоступно.';
  if (command.groupIds.some((id) => !state.groups.some((x) => x.id === id))) e.scope = 'Группа больше недоступна.';
  if (replacementNames(command, state).length && !command.replaceAssignments) e.scope = 'Подтвердите замену текущих ответственных.';
  return e;
}
export function applyUser(command: UserCommand, state: OrganizationState, id: number): ManagedStaff {
  const previous = state.users.find((x) => x.id === id);
  const email = command.email.trim().toLowerCase();
  const changed = previous && (previous.email !== email || previous.role !== command.role || previous.active !== command.active);
  const value = {
    id,
    name: command.name.trim(),
    email,
    role: command.role,
    active: command.active,
    revision: (previous?.revision ?? 0) + 1,
    accessRevision: (previous?.accessRevision ?? 1) + (changed ? 1 : 0)
  };
  state.users = previous ? state.users.map((x) => (x.id === id ? value : x)) : [...state.users, value];
  state.institutions = state.institutions.map((x) => {
    const curatorId =
      command.active && command.role === 'curator' && command.institutionIds.includes(x.id) ? id : x.curatorId === id ? null : x.curatorId;
    const headId =
      command.active && command.role === 'head' && command.institutionIds.includes(x.id) ? id : x.headId === id ? null : x.headId;
    return curatorId === x.curatorId && headId === x.headId ? x : { ...x, curatorId, headId, revision: x.revision + 1 };
  });
  state.groups = state.groups.map((x) => {
    const teacherId =
      command.active && command.role === 'teacher' && command.groupIds.includes(x.id) ? id : x.teacherId === id ? null : x.teacherId;
    return teacherId === x.teacherId ? x : { ...x, teacherId, revision: x.revision + 1 };
  });
  return value;
}
