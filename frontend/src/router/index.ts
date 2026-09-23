import { createRouter, createWebHistory } from 'vue-router';
import MainRoutes from './MainRoutes';
import PublicRoutes, { ServiceRoutes } from './PublicRoutes';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { isStaffRole } from '@/modules/morefoto/types';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [MainRoutes, PublicRoutes, ServiceRoutes],
  scrollBehavior: (to, from, savedPosition) => savedPosition ?? (to.path === from.path ? false : { top: 0 })
});

router.beforeEach(async (to) => {
  if (!isMockApiEnabled && to.meta.demoOnly) return '/feature-unavailable';
  const auth = useAuthStore();
  auth.restoreSession();
  if (auth.isAuthenticated && (to.meta.requiresAuth || to.path === '/login')) {
    try {
      await auth.ensureProfile();
    } catch {
      auth.returnUrl = to.meta.requiresAuth ? to.fullPath : null;
      return to.path === '/login' ? true : '/login?reason=session-expired';
    }
  }
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    auth.returnUrl = to.fullPath;
    return '/login';
  }
  if (to.path.startsWith('/cabinet') && auth.isAuthenticated) {
    const role = auth.user?.role;
    if (!isStaffRole(role) || (to.meta.staffRoles && !to.meta.staffRoles.includes(role))) {
      return '/access-unavailable';
    }
  }
  if (to.path === '/login' && auth.isAuthenticated) {
    return auth.homePath;
  }
  if (!isMockApiEnabled && to.path.startsWith('/cabinet')) {
    if (
      ![
        'Catalog',
        'Users',
        'GroupConditions',
        'CabinetProfile',
        'OrganizationList',
        'CabinetInstitution',
        'OrganizationShoot',
        'PhotoWorkspace',
        'StaffRequests',
        'StaffRequest',
        'WorkOrders',
        'WorkOrder',
        'Links'
      ].includes(String(to.name))
    )
      return auth.homePath;
    if (['Catalog', 'GroupConditions'].includes(String(to.name)) && !auth.user?.permissions?.includes('catalog.manage'))
      return '/access-unavailable';
    if (to.path === '/cabinet/users' && !auth.user?.permissions?.includes('staff.manage')) return '/access-unavailable';
    if (to.name === 'PhotoWorkspace' && !auth.user?.permissions?.includes('media.manage')) return '/access-unavailable';
    if (['WorkOrders', 'WorkOrder'].includes(String(to.name)) && !auth.user?.permissions?.includes('order.read'))
      return '/access-unavailable';
  }
  return true;
});
