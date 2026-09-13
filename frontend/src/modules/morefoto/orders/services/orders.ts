import { projectProduction } from '../../production/projection';
import type { ProductionState } from '../../production/types';
import { isMockApiEnabled } from '@/mocks/config';
import { readDemo, writeDemo } from '../../mocks/storage';
import { simulateRequest } from '../../mocks/runtime';
import type { OrderSnapshot } from '../types';
export function randomKey(): string {
  return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
export function readOrders(): OrderSnapshot[] {
  if (!isMockApiEnabled) return [];
  return projectProduction(
    readDemo<OrderSnapshot[]>('orders:v1', []),
    readDemo<ProductionState>('production:v1', { jobs: [], operations: [] })
  );
}
export class OrderUnavailableError extends Error {}
export function resolveOrder(accessKey: string): OrderSnapshot {
  const order = /^[a-f0-9]{32}$/.test(accessKey) ? readOrders().find((item) => item.accessKey === accessKey) : undefined;
  if (!order) throw new OrderUnavailableError('Заказ по этой ссылке недоступен. Откройте личную ссылку, полученную при оформлении.');
  return order;
}
export async function loadOrder(accessKey: string): Promise<OrderSnapshot> {
  await simulateRequest();
  return resolveOrder(accessKey);
}
export function saveOrder(order: OrderSnapshot): void {
  const orders = readOrders();
  writeDemo(
    'orders:v1',
    orders.some((item) => item.id === order.id) ? orders.map((item) => (item.id === order.id ? order : item)) : [...orders, order]
  );
}
// Web Locks serialize browser tabs; the promise queue also covers environments without that API.
let mutationQueue: Promise<unknown> = Promise.resolve();
export async function withOrderLock<T>(action: () => T | Promise<T>): Promise<T> {
  if (navigator.locks) return navigator.locks.request('morefoto-demo-orders', action);
  const result = mutationQueue.then(action, action);
  mutationQueue = result.catch(() => undefined);
  return result;
}
