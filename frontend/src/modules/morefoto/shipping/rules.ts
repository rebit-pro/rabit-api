import { packageIds, printCount } from '../production/rules.ts';
import { parseTransmission, calendarDays } from '../handoff/rules.ts';
import { pendingDelivery } from './identity.ts';
import type { ProductionGroup, ProductionState } from '../production/types.js';
import type { DeliveryCommand, DeliveryErrors, DeliveryGroup, TransferGroup } from './types.js';
export function inspectDelivery(item: ProductionGroup, state: ProductionState, now: string) {
  const { job, plan, group } = item,
    version = job?.versions.slice(-1)[0];
  const sent = !!version && (state.transfers ?? []).some((t) => t.groups.some((g) => g.jobId === job!.id && g.version === version.number));
  const pending = version ? pendingDelivery(version.plan.rows, state) : [];
  const problem = !plan.closed
    ? 'Дождитесь закрытия группы.'
    : !version
      ? 'Сформируйте печатное задание.'
      : version.plan.signature !== plan.signature
        ? 'Состав изменился: нужна новая версия.'
        : !version.startedAt
          ? 'Сначала учтите запуск печати.'
          : !version.plan.rows.length
            ? 'В версии нет физических позиций.'
            : packageIds(version).some((id) => !version.packages[id])
              ? 'Сверьте все пакеты этой версии.'
              : '';
  const ready = !!version?.ready && !problem;
  const delivered = !!version?.plan.rows.length && !pending.length && !problem;
  const status = delivered
    ? 'Передано в учреждение'
    : problem
      ? !plan.closed
        ? group.state === 'preparing'
          ? 'Подготовка группы'
          : 'Приём открыт'
        : problem
      : ready
        ? 'Готово к передаче'
        : 'Ожидает отметки готовности';
  const signature = JSON.stringify([job?.id, job?.revision, plan.signature, version?.ready, version?.packages]);
  const deadline = plan.deliveryAt ?? (group.state !== 'preparing' && group.closesAt ? calendarDays(group.closesAt, 7) : null);
  const view: DeliveryGroup = {
    id: group.id,
    name: group.name,
    kind: group.kind,
    institutionId: group.institutionId,
    institutionName: item.institutionName,
    shootId: group.shootId,
    shootName: item.shootName,
    deadline,
    overdue: !!deadline && Date.parse(deadline) < Date.parse(now) && !delivered,
    status,
    packs: new Set(pending.map((r) => r.orderId)).size,
    prints: printCount(pending),
    readyAt: version?.ready?.at ?? null,
    jobNumber: job?.number ?? '',
    version: version?.number ?? 0,
    signature,
    canReady: !problem && !version?.ready && pending.length > 0,
    canUnready: !!version?.ready && !sent,
    problem
  };
  const transferGroup: TransferGroup | null =
    ready && pending.length
      ? {
          groupId: group.id,
          groupName: group.name,
          kind: group.kind,
          jobId: job!.id,
          jobNumber: job!.number,
          version: version!.number,
          deadline: plan.deliveryAt,
          readyAt: version!.ready!.at,
          rows: pending
        }
      : null;
  return { view, transferGroup, version, job };
}
export function deliveryErrors(command: DeliveryCommand, now: string, earliest: string): DeliveryErrors {
  const errors: DeliveryErrors = {};
  if (!command.confirmed) errors.confirmed = 'Подтвердите проверку состава и фактического события.';
  if (command.comment.trim().length < 3 || command.comment.trim().length > 500)
    errors.comment = 'Введите комментарий от 3 до 500 символов.';
  if (command.kind !== 'unready') {
    const at = parseTransmission(command.date, now);
    if (!at || Date.parse(at) < Math.floor(Date.parse(earliest) / 60000) * 60000)
      errors.date = 'Укажите время не раньше завершения предыдущего этапа и не в будущем (МСК).';
    if (command.responsible.trim().length < 2 || command.responsible.trim().length > 100)
      errors.responsible = 'Укажите ответственного: от 2 до 100 символов.';
  }
  if (command.kind === 'transfer' && (command.receiver.trim().length < 2 || command.receiver.trim().length > 100))
    errors.receiver = 'Укажите, кто принял продукцию в учреждении: от 2 до 100 символов.';
  return errors;
}
