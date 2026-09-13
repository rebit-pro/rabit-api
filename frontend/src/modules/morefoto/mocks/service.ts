import { readOrders } from '../orders/services/orders';
import { groupFinancials, sumGroups } from '../dashboard/rules';
import { dashboardSnapshot } from '../dashboard/snapshot';
import type { LoginRequest, LoginResponse } from '@/api/auth';
import type { ScopeSnapshot } from '../types';
import { demoPassword } from './fixtures';
import { readOrganization } from '../organization/repository';
import { getDemoNow } from './clock';
import { scopedOrganization } from '../organization/rules';
import { simulateRequest } from './runtime';

interface Session {
  userId: number;
  accessRevision?: number;
  expiresAt: string;
}

const sessionKey = 'morefoto:demo:sessions:v1';

export class DemoError extends Error {
  constructor(
    public status: number,
    message: string
  ) {
    super(message);
  }
}

function sessions(): Record<string, Session> {
  try {
    const value = JSON.parse(localStorage.getItem(sessionKey) ?? '{}');
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  } catch {
    return {};
  }
}

export async function loginWithDemo(data: LoginRequest): Promise<LoginResponse> {
  await simulateRequest();
  const account = readOrganization().users.find((item) => item.active && item.email === data.email.trim().toLowerCase());
  if (!account || data.password !== demoPassword) {
    throw new DemoError(401, 'Неверный email или пароль');
  }
  const expiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString();
  const token = 'morefoto-demo-' + Array.from(crypto.getRandomValues(new Uint32Array(4))).join('-');
  localStorage.setItem(
    sessionKey,
    JSON.stringify({
      ...sessions(),
      [token]: {
        userId: account.id,
        expiresAt,
        accessRevision: account.accessRevision
      }
    })
  );
  return {
    token,
    expiresAt,
    user: {
      id: account.id,
      name: account.name,
      email: account.email,
      role: account.role
    }
  };
}

export function logoutWithDemo(token: string): void {
  const state = sessions();
  delete state[token];
  localStorage.setItem(sessionKey, JSON.stringify(state));
}

export function requireDemoAccount(token: string) {
  const session = sessions()[token];
  if (!session || !(Date.parse(session.expiresAt) > Date.now())) {
    throw new DemoError(401, 'Сессия истекла. Войдите снова.');
  }
  const account = readOrganization().users.find((item) => item.id === session.userId);
  if (account && (!account.active || account.accessRevision !== (session.accessRevision ?? 1)))
    throw new DemoError(401, 'Доступ изменён. Войдите снова.');
  if (!account) throw new DemoError(403, 'Доступ не назначен.');
  return account;
}
export async function getScopeWithDemo(token: string): Promise<ScopeSnapshot> {
  await simulateRequest();
  const actor = requireDemoAccount(token),
    organization = readOrganization(),
    now = getDemoNow();
  const scope = scopedOrganization(organization, actor, now);
  const dashboard = dashboardSnapshot(scope, organization, actor, now);
  if (actor.role === 'teacher') return structuredClone({ ...scope, dashboard });
  const groupTotals = groupFinancials(scope, readOrders());
  return structuredClone({
    ...scope,
    dashboard,
    groupTotals,
    totals: Object.fromEntries(
      scope.institutions.map((i) => [
        i.id,
        sumGroups(
          scope.groups.filter((g) => g.institutionId === i.id),
          groupTotals
        )
      ])
    )
  });
}
