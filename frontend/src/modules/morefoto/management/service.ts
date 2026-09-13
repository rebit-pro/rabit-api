import { isMockApiEnabled } from '@/mocks/config';
import { DemoError, requireDemoAccount } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { readDemo, writeDemo } from '../mocks/storage';
import { getCatalog } from '../commerce/mocks/catalog';
import { readOrganization, writeOrganization } from '../organization/repository';
import type { Catalog } from '../commerce/types';
import type { GroupConditions, ManagementCommand, ManagementErrors } from './types';
import { applyUser, assignmentSignature, checkedPrice, conditionsErrors, productErrors, productValue, userErrors } from './rules';
export const managementChangedEvent = 'morefoto:management:changed';
export class ManagementValidationError extends Error {
  constructor(public errors: ManagementErrors) {
    super('Проверьте выделенные поля.');
  }
}
function actor(token: string) {
  if (!isMockApiEnabled) throw new DemoError(403, 'Управление доступно только в демонстрации.');
  const account = requireDemoAccount(token);
  if (account.role !== 'organizer') throw new DemoError(403, 'Изменять каталог, условия и пользователей может только организатор.');
  return account;
}
function conflict() {
  throw new DemoError(
    409,
    'Данные изменены в другой вкладке. Черновик сохранён. Нажмите «Загрузить актуальные данные» и повторите изменения.'
  );
}
export async function saveManagement(token: string, command: ManagementCommand): Promise<void> {
  actor(token);
  await simulateRequest();
  const commit = () => {
    const account = actor(token);
    const signature = JSON.stringify(command);
    function completed(operations: { requestId: string; actorId: number; signature?: string }[] | undefined) {
      const previous = operations?.find((x) => x.requestId === command.requestId && x.actorId === account.id);
      if (previous && previous.signature !== signature) conflict();
      return !!previous;
    }
    if (!command.requestId) throw new DemoError(400, 'Некорректный запрос.');
    if (command.kind === 'user') {
      const state = readOrganization();
      if (completed(state.userOperations)) return;
      const existing = state.users.find((x) => x.id === command.id);
      if (command.id !== null && !existing) throw new DemoError(404, 'Пользователь не найден.');
      if ((existing?.revision ?? null) !== command.revision || command.assignmentSignature !== assignmentSignature(state)) conflict();
      const errors = userErrors(command, state, account.id);
      if (Object.keys(errors).length) throw new ManagementValidationError(errors);
      const id = existing?.id ?? Math.max(100, ...state.users.map((x) => x.id)) + 1;
      applyUser(command, state, id);
      state.userOperations.push({ requestId: command.requestId, actorId: account.id, id, signature });
      writeOrganization(state);
    } else {
      const catalog = getCatalog() as Catalog & { managementOperations?: { requestId: string; actorId: number; signature?: string }[] };
      if (completed(catalog.managementOperations)) return;
      const all = readDemo<Record<string, GroupConditions & { operations?: { requestId: string; actorId: number; signature?: string }[] }>>(
        'group-conditions:v1',
        {}
      );
      const entry = command.kind === 'conditions' && command.groupId ? all[command.groupId] : undefined;
      if (completed(entry?.operations)) return;
      if (catalog.revision !== command.revision) conflict();
      const errors = command.kind === 'product' ? productErrors(command, catalog) : conditionsErrors(command, catalog);
      if (Object.keys(errors).length) throw new ManagementValidationError(errors);
      if (command.kind === 'product') {
        const value = productValue(command);
        catalog.products = catalog.products.some((x) => x.id === value.id)
          ? catalog.products.map((x) => (x.id === value.id ? value : x))
          : [...catalog.products, value];
      } else if (command.groupId) {
        const state = readOrganization();
        const group = state.groups.find(
          (x) => x.id === command.groupId && x.shootId === command.shootId && x.institutionId === command.institutionId
        );
        if (!group) throw new DemoError(404, 'Группа этой съёмки не найдена.');
        if ((entry?.revision ?? 0) !== command.conditionsRevision) conflict();
        all[group.id] = {
          revision: (entry?.revision ?? 0) + 1,
          inherit: command.inherit,
          products: Object.fromEntries(
            command.products.map((p) => [p.id, { price: checkedPrice(p.price) ?? 0, active: p.active, staffDiscount: p.staffDiscount }])
          ),
          giftThreshold: command.giftEnabled ? (checkedPrice(command.giftThreshold) ?? 0) : 0,
          giftForStaff: command.giftForStaff,
          operations: [...(entry?.operations ?? []), { requestId: command.requestId, actorId: account.id, signature }]
        };
        writeDemo('group-conditions:v1', all);
      } else {
        catalog.products = catalog.products.map((p) => {
          const value = command.products.find((x) => x.id === p.id)!;
          return { ...p, price: checkedPrice(value.price)!, active: value.active, staffDiscount: value.staffDiscount };
        });
        catalog.giftThreshold = command.giftEnabled ? checkedPrice(command.giftThreshold)! : 0;
        catalog.giftForStaff = command.giftForStaff;
      }
      if (command.kind === 'product' || !command.groupId) {
        catalog.revision++;
        catalog.managementOperations = [
          ...(catalog.managementOperations ?? []),
          { requestId: command.requestId, actorId: account.id, signature }
        ];
        writeDemo('catalog:v1', catalog);
      }
    }
    window.dispatchEvent(new Event(managementChangedEvent));
  };
  // Shares the assignment lock with R07; catalog and conditions also serialize against each other.
  return navigator.locks ? navigator.locks.request('morefoto:organization:write', commit) : commit();
}
