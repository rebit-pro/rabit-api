import { isMockApiEnabled } from '@/mocks/config';
import type { ScopeSnapshot } from '../types';

export async function loadCabinetScope(token: string): Promise<ScopeSnapshot> {
  if (!isMockApiEnabled) {
    throw new Error('Данные кабинета ещё не подключены. Обратитесь к организатору.');
  }
  const { getScopeWithDemo } = await import('../mocks/service');
  return getScopeWithDemo(token);
}
