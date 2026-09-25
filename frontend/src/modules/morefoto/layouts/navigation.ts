import type { StaffRole } from '../types';

export interface NavigationItem {
  title: string;
  to: string;
  icon: string;
  /** Things waiting for the user in this section (U5 summaries); absent or zero shows no badge. */
  count?: number;
}

export interface NavigationGroup {
  title: string;
  items: NavigationItem[];
}

export interface NavigationContext {
  role: StaffRole | null;
  permissions: readonly string[];
  /** Demo mode shows the product screens that have no live API yet. */
  demo: boolean;
  /** Waiting items by section path, e.g. groups to check under «Ссылки и сроки». */
  counters?: Readonly<Record<string, number>>;
}

const item = (title: string, to: string, icon: string): NavigationItem => ({ title, to, icon });

/**
 * Cabinet menu grouped into daily work and settings. Visibility mirrors the router guards: a role or permission
 * hides an item, it never grants access. The profile lives in the user block and the user menu.
 */
export function cabinetNavigation({ role, permissions, demo, counters = {} }: NavigationContext): NavigationGroup[] {
  if (null === role) return [];
  const can = (permission: string) => permissions.includes(permission);
  const work: NavigationItem[] = [];
  const settings: NavigationItem[] = [];
  work.push(item('teacher' === role ? 'Мои группы' : 'Обзор', '/cabinet/overview', 'mdi-view-dashboard-outline'));
  if (demo) {
    if ('organizer' === role) work.push(item('Учреждения', '/cabinet/institutions', 'mdi-home-city-outline'));
    work.push(item('Ссылки и сроки', '/cabinet/links', 'mdi-link-variant'));
    if ('head' !== role) work.push(item('Списки сотрудников', '/cabinet/staff-requests', 'mdi-account-check-outline'));
    if ('organizer' === role || 'curator' === role) {
      work.push(
        item('Заказы', '/cabinet/orders', 'mdi-receipt-text-outline'),
        item('Производство', '/cabinet/production', 'mdi-printer-outline'),
        item('Обращения', '/cabinet/support', 'mdi-message-text-outline')
      );
    }
    work.push(item('Доставка', '/cabinet/delivery', 'mdi-truck-delivery-outline'));
    if ('organizer' === role) {
      settings.push(
        item('Каталог и цены', '/cabinet/catalog', 'mdi-tag-outline'),
        item('Сотрудники', '/cabinet/users', 'mdi-account-group-outline')
      );
    }
  } else {
    if ('teacher' !== role) work.push(item('Учреждения', '/cabinet/institutions', 'mdi-home-city-outline'));
    work.push(item('Ссылки и сроки', '/cabinet/links', 'mdi-link-variant'));
    // DS-14: the head reads the staff lists of their institutions without changing them.
    work.push(item('Списки сотрудников', '/cabinet/staff-requests', 'mdi-account-check-outline'));
    if (can('order.read')) {
      // G1: the payment registry has the same scope as orders.
      work.push(
        item('Заказы', '/cabinet/orders', 'mdi-receipt-text-outline'),
        item('Платежи', '/cabinet/payments', 'mdi-credit-card-outline')
      );
    }
    if (can('catalog.manage')) settings.push(item('Каталог и цены', '/cabinet/catalog', 'mdi-tag-outline'));
    if (can('staff.manage')) settings.push(item('Сотрудники', '/cabinet/users', 'mdi-account-group-outline'));
  }
  const counted = (items: NavigationItem[]) => items.map((entry) => (counters[entry.to] ? { ...entry, count: counters[entry.to] } : entry));
  return [
    { title: 'Работа', items: counted(work) },
    { title: 'Настройки', items: counted(settings) }
  ].filter((group) => group.items.length > 0);
}
