import { currentLines, financials, lateDecision, settlement } from '../settlement/rules.ts';
import { calendarDays, currentGroupState } from '../handoff/rules.ts';
import type { PlanInput, PrintPlan, PrintRow, PrintJob, PrintVersion, RunRow } from './types.js';
export function buildPlan({ group, organization, photos, orders, state, now }: PlanInput): PrintPlan {
  const rows: PrintRow[] = [],
    excluded: PrintPlan['excluded'] = [];
  let digitalCount = 0;
  for (const order of orders.filter((o) => o.groupId === group.id).sort((a, b) => a.id.localeCompare(b.id))) {
    const lines = currentLines(order),
      physical = lines.filter((l) => l.product.kind === 'physical' && l.quantity !== 0);
    digitalCount += lines.filter((l) => l.product.kind !== 'physical').length;
    if (!physical.length) continue;
    const s = settlement(order);
    const tracked = state.jobs.some((j) => j.versions.some((v) => v.plan.rows.some((r) => r.orderId === order.id)));
    const reason =
      order.paymentStatus !== 'paid' || !order.paidAt
        ? 'Оплата не подтверждена'
        : s.refunds.some((r) => r.status === 'pending')
          ? 'Ожидается результат возврата'
          : financials([order]).net <= 0
            ? 'Оплата полностью возвращена'
            : s.hold
              ? 'Исполнение приостановлено'
              : order.latePayment && lateDecision(order) !== 'fulfil'
                ? 'Поздняя оплата требует решения'
                : s.needsReprint && !s.reprintApproved
                  ? 'Изменение после печати требует согласования'
                  : ['ready', 'delivered'].includes(order.productionStatus) && !tracked
                    ? 'Заказ уже готов или передан'
                    : order.productionStatus === 'printing' && !tracked
                      ? 'Печать начата вне реестра: нужна сверка'
                      : '';
    if (reason) {
      excluded.push({ orderId: order.id, number: order.number, reason });
      continue;
    }
    const next: PrintRow[] = [];
    for (const line of physical) {
      if (
        !line.photo ||
        !line.childCode ||
        line.photoId !== line.photo.id ||
        !Number.isSafeInteger(line.quantity) ||
        line.quantity < 1 ||
        !Number.isSafeInteger(line.product.printCount) ||
        line.product.printCount < 1 ||
        !Number.isSafeInteger(line.quantity * line.product.printCount)
      )
        break;
      const photo = photos.photos.find((p) => p.id === line.photoId);
      const source = organization.groups.find((g) => g.id === photo?.originalGroupId && g.institutionId === group.institutionId) ?? group;
      const transfer = (photos.staffRequests ?? [])
        .filter((r) => r.status === 'transferred' && r.institutionId === group.institutionId)
        .flatMap((r) => r.results ?? [])
        .find((r) => r.photoIds.includes(line.photoId!) && r.fromGroupId === source.id);
      const format = line.product.format || line.product.name;
      next.push({
        key: JSON.stringify([order.id, line.id, line.photoId, line.product.id, format, line.product.printCount]),
        orderId: order.id,
        orderNumber: order.number,
        lineId: line.id,
        photoId: line.photoId,
        photoCode: line.photo.code,
        productName: line.product.name,
        format,
        quantity: line.quantity,
        perUnit: line.product.printCount,
        prints: line.quantity * line.product.printCount,
        childCode: line.childCode,
        sourceGroupId: source.id,
        sourceGroupName: source.name,
        sourceChildCode: transfer?.fromChildCode ?? line.childCode,
        purchaseGroupName: group.name,
        audience: order.audience
      });
    }
    if (next.length !== physical.length || new Set(next.map((r) => r.key)).size !== next.length)
      excluded.push({ orderId: order.id, number: order.number, reason: 'Некорректный кадр или количество: нужна сверка заказа' });
    else rows.push(...next.sort((a, b) => a.key.localeCompare(b.key)));
  }
  const closesAt = group.closesAt,
    closed =
      currentGroupState(group, now) === 'closed' &&
      !!closesAt &&
      Number.isFinite(Date.parse(closesAt)) &&
      Date.parse(closesAt) <= Date.parse(now);
  return {
    rows,
    excluded,
    digitalCount,
    closed,
    closesAt,
    deliveryAt: closed && closesAt ? calendarDays(closesAt, 7) : null,
    signature: JSON.stringify([group.id, group.institutionId, group.shootId, closed, closesAt, rows])
  };
}
export function printedRows(job?: PrintJob | null): Map<string, RunRow> {
  const printed = new Map<string, RunRow>();
  for (const version of job?.versions ?? [])
    if (version.startedAt)
      for (const row of version.runRows) {
        if (row.next <= 0) continue;
        const total = (printed.get(row.key)?.prints ?? 0) + row.next;
        printed.set(row.key, { ...row, prints: total, next: 0, prior: total });
      }
  return printed;
}
export function composeVersion(plan: PrintPlan, job: PrintJob | null, at: string, actor: string, reason: string): PrintVersion {
  const printed = printedRows(job),
    previous = job?.versions.slice(-1)[0];
  const runRows = plan.rows.map((r) => ({
    ...r,
    prior: printed.get(r.key)?.prints ?? 0,
    next: Math.max(0, r.prints - (printed.get(r.key)?.prints ?? 0))
  }));
  const surplus = [...printed.values()]
    .filter((r) => r.prints > (plan.rows.find((v) => v.key === r.key)?.prints ?? 0))
    .map((r) => ({ ...r, next: r.prints - (plan.rows.find((v) => v.key === r.key)?.prints ?? 0) }));
  const packages: PrintVersion['packages'] = {};
  for (const [id, checked] of Object.entries(previous?.packages ?? {})) {
    const currentRows = plan.rows.filter((r) => r.orderId === id);
    if (currentRows.length && JSON.stringify(currentRows) === JSON.stringify(previous!.plan.rows.filter((r) => r.orderId === id)))
      packages[id] = checked;
  }
  return { number: (previous?.number ?? 0) + 1, createdAt: at, actor, reason, plan, runRows, surplus, packages };
}
export const printCount = (rows: PrintRow[]) => rows.reduce((sum, r) => sum + r.prints, 0);
export const newCount = (version: PrintVersion) => version.runRows.reduce((sum, r) => sum + r.next, 0);
export const packageIds = (version: PrintVersion) => [...new Set(version.plan.rows.map((r) => r.orderId))];
function cell(value: string | number): string {
  let text = String(value);
  if (/^[\s]*[=+@-]/.test(text)) text = "'" + text;
  return '"' + text.replace(/"/g, '""') + '"';
}
export function printCsv(job: PrintJob, version: PrintVersion): string {
  const header = [
    'Задание',
    'Версия',
    'Заказ',
    'Кадр',
    'Формат',
    'Единиц',
    'Отпечатков в единице',
    'Отпечатков в позиции',
    'Запущено ранее',
    'К новой печати этой версии',
    'Код покупателя',
    'Исходная группа',
    'Исходный код',
    'Группа покупки',
    'Аудитория'
  ];
  const rows = version.runRows.map((r) => [
    job.number,
    version.number,
    r.orderNumber,
    r.photoCode,
    r.format,
    r.quantity,
    r.perUnit,
    r.prints,
    r.prior,
    r.next,
    r.childCode,
    r.sourceGroupName,
    r.sourceChildCode,
    r.purchaseGroupName,
    r.audience === 'staff' ? 'Сотрудник' : 'Родитель'
  ]);
  return '\ufeff' + [header, ...rows].map((row) => row.map(cell).join(';')).join('\r\n');
}
