import type { ProductionGroup } from './types';
export function productionState(item: ProductionGroup): string {
  const version = item.job?.versions.slice(-1)[0];
  if (item.group.state === 'preparing') return 'Подготовка группы';
  if (!item.plan.closed) return 'Приём открыт';
  if (version && version.plan.signature !== item.plan.signature) return 'Состав изменился';
  if (!version) return item.plan.rows.length ? 'Можно сформировать' : 'Нет позиций для печати';
  if (!version.startedAt) return 'Задание подготовлено';
  return Object.keys(version.packages).length === new Set(version.plan.rows.map((r) => r.orderId)).size
    ? 'Пакеты скомплектованы'
    : 'Комплектация';
}
