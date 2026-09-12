import { readDemo, writeDemo } from '../mocks/storage';
import { simulateRequest } from '../mocks/runtime';
import { DemoError } from '../mocks/service';
import { handoffAccess, handoffLock } from '../handoff/scope';
import { readPhotos } from '../photos/repository';
import { readOrders, withOrderLock, randomKey } from '../orders/services/orders';
import { buildPlan, composeVersion, newCount, packageIds, printCsv } from './rules';
import type { PrintJob, ProductionCommand, ProductionState, ProductionWorkspace } from './types';
export const productionKey = 'production:v1';
export const readProduction = (): ProductionState => readDemo(productionKey, { jobs: [], operations: [] });
export function productionAccess(token: string) {
  const access = handoffAccess(token);
  if (!['organizer', 'curator'].includes(access.account.role)) throw new DemoError(403, 'Производство доступно организатору и куратору.');
  const state = readProduction(),
    orders = readOrders(),
    photos = readPhotos();
  const groups = access.organization.groups
    .filter((g) => access.scope.groups.some((s) => s.id === g.id))
    .map((group) => {
      const job =
        state.jobs.find((j) => j.groupId === group.id && j.institutionId === group.institutionId && j.shootId === group.shootId) ?? null;
      return {
        group,
        institutionName: access.organization.institutions.find((i) => i.id === group.institutionId)!.name,
        shootName: access.organization.shoots.find((s) => s.id === group.shootId)?.name ?? group.shootName,
        job,
        plan: buildPlan({ group, organization: access.organization, photos, orders, state, now: access.now })
      };
    });
  return { ...access, state, groups };
}
export async function loadProduction(token: string): Promise<ProductionWorkspace> {
  await simulateRequest();
  const { account, groups } = productionAccess(token);
  return { editable: account.role === 'organizer', actorId: account.id, groups };
}
function conflict(): never {
  throw new DemoError(409, 'Состав или версия изменились. Обновите данные и проверьте задание заново.');
}
export async function saveProduction(token: string, command: ProductionCommand): Promise<string> {
  await simulateRequest();
  return handoffLock(() =>
    withOrderLock(() => {
      const access = productionAccess(token),
        { account, state, now } = access;
      if (account.role !== 'organizer') throw new DemoError(403, 'Изменять производство может организатор.');
      const item = access.groups.find((g) => g.group.id === command.groupId);
      if (!item) throw new DemoError(403, 'Группа недоступна.');
      const signature = JSON.stringify(command),
        prior = state.operations.find((o) => o.id === command.id);
      if (prior) {
        if (prior.signature !== signature || prior.actorId !== account.id) conflict();
        return prior.jobId;
      }
      if (!command.id || command.id.length > 128 || !command.reason.trim() || command.reason.trim().length > 500)
        throw new DemoError(422, 'Укажите причину или комментарий, до 500 символов.');
      let job = item.job;
      if ((job?.revision ?? 0) !== command.revision || item.plan.signature !== command.signature) conflict();
      if (!item.plan.closed) throw new DemoError(422, 'Дождитесь закрытия группы.');
      if (state.jobs.some((j) => j.groupId === item.group.id && j.id !== job?.id))
        throw new DemoError(409, 'Привязка группы изменилась: сначала сверяйте существующее задание.');
      const actor = account.name,
        reason = command.reason.trim();
      if (command.kind === 'version') {
        if (!job && !item.plan.rows.length) throw new DemoError(422, 'Нет оплаченных физических позиций для печати.');
        if (!job) {
          job = {
            id: randomKey(),
            number: 'ПЗ-' + String(state.jobs.length + 1).padStart(4, '0'),
            groupId: item.group.id,
            institutionId: item.group.institutionId,
            shootId: item.group.shootId,
            revision: 0,
            versions: [],
            history: []
          };
          state.jobs.push(job);
        }
        if (job.versions.slice(-1)[0]?.plan.signature !== item.plan.signature) {
          const version = composeVersion(item.plan, job, now, actor, reason);
          job.versions.push(version);
          job.revision++;
          job.history.push({
            at: now,
            actor,
            text: `Сформирована версия ${version.number}: ${newCount(version)} новых отпечатков. ${reason}`
          });
        }
      } else {
        if (!job) conflict();
        const version = job.versions.slice(-1)[0]!;
        if (version.plan.signature !== item.plan.signature) conflict();
        if (command.kind === 'start') {
          if (!version.startedAt) {
            version.startedAt = now;
            version.startedBy = actor;
            job.revision++;
            job.history.push({ at: now, actor, text: `Запуск версии ${version.number}: ${newCount(version)} новых отпечатков. ${reason}` });
          }
        } else if (command.kind === 'pack') {
          if (version.ready) throw new DemoError(409, 'Сначала снимите готовность в разделе доставки.');
          if (
            !version.startedAt ||
            !command.orderId ||
            !packageIds(version).includes(command.orderId) ||
            typeof command.packed !== 'boolean'
          )
            throw new DemoError(422, 'Сначала учтите запуск текущей версии и выберите заказ.');
          if (Boolean(version.packages[command.orderId]) !== command.packed) {
            if (command.packed) version.packages[command.orderId] = { at: now, actor };
            else delete version.packages[command.orderId];
            job.revision++;
            const number = version.plan.rows.find((r) => r.orderId === command.orderId)!.orderNumber;
            job.history.push({
              at: now,
              actor,
              text: `Версия ${version.number}, заказ ${number}: ${command.packed ? 'пакет скомплектован' : 'комплектация снята'}. ${reason}`
            });
          }
        } else throw new DemoError(422, 'Неизвестное действие.');
      }
      state.operations.push({ id: command.id, signature, actorId: account.id, jobId: job!.id });
      writeDemo(productionKey, state);
      if (readDemo('production:lose-response-once', false)) {
        writeDemo('production:lose-response-once', false);
        throw new DemoError(503, 'Ответ потерян. Повторите то же действие: результат уже сохранён.');
      }
      return job!.id;
    })
  );
}
export async function exportProduction(token: string, jobId: string, versionNumber: number): Promise<{ name: string; csv: string }> {
  await simulateRequest();
  const { groups } = productionAccess(token),
    job: PrintJob | undefined = groups.find((g) => g.job?.id === jobId)?.job ?? undefined;
  const version = job?.versions.find((v) => v.number === versionNumber);
  if (!job || !version) throw new DemoError(403, 'Задание или версия недоступны.');
  return { name: `${job.number}-v${version.number}.csv`, csv: printCsv(job, version) };
}
