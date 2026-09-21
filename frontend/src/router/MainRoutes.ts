import type { RouteRecordRaw } from 'vue-router';
import { isMockApiEnabled } from '@/mocks/config';

const MainRoutes: RouteRecordRaw = {
  path: '/cabinet',
  meta: { requiresAuth: true },
  redirect: '/cabinet/overview',
  component: () => import('@/modules/morefoto/layouts/CabinetLayout.vue'),
  children: [
    {
      name: 'Delivery',
      path: 'delivery',
      component: () => import('@/modules/morefoto/views/DeliveryPage.vue'),
      meta: { title: 'Готовность и доставка' }
    },
    {
      name: 'Production',
      path: 'production/:groupId?',
      component: () => import('@/modules/morefoto/views/ProductionPage.vue'),
      meta: {
        title: 'Производство и комплектация',
        staffRoles: ['organizer', 'curator']
      }
    },
    {
      name: 'GroupSummary',
      path: 'groups/:groupId',
      component: () => import('@/modules/morefoto/views/GroupDashboardPage.vue'),
      meta: { title: 'Сводка группы' }
    },
    {
      name: 'WorkOrders',
      path: 'orders',
      component: () => import('@/modules/morefoto/views/CuratorPage.vue'),
      meta: { title: 'Заказы', staffRoles: ['organizer', 'curator'] }
    },
    {
      name: 'WorkOrder',
      path: 'orders/:orderId',
      component: () => import('@/modules/morefoto/views/CuratorPage.vue'),
      meta: { title: 'Заказ', staffRoles: ['organizer', 'curator'] }
    },
    {
      name: 'SupportInbox',
      path: 'support',
      component: () => import('@/modules/morefoto/views/CuratorPage.vue'),
      meta: { title: 'Обращения', staffRoles: ['organizer', 'curator'] }
    },
    {
      name: 'SupportCase',
      path: 'support/:ticketId',
      component: () => import('@/modules/morefoto/views/CuratorPage.vue'),
      meta: { title: 'Обращение', staffRoles: ['organizer', 'curator'] }
    },
    {
      name: 'Links',
      path: 'links',
      component: () => import('@/modules/morefoto/views/LinksPage.vue'),
      meta: { title: 'Ссылки и сроки' }
    },
    {
      name: 'StaffRequests',
      path: 'staff-requests',
      component: () => import('@/modules/morefoto/views/StaffRequestsPage.vue'),
      meta: {
        title: 'Заявки на списки сотрудников',
        staffRoles: ['organizer', 'curator', 'teacher']
      }
    },
    {
      name: 'StaffRequest',
      path: 'staff-requests/:requestId',
      component: () => import('@/modules/morefoto/views/StaffRequestsPage.vue'),
      meta: {
        title: 'Заявка на список сотрудников',
        staffRoles: ['organizer', 'curator', 'teacher']
      }
    },
    {
      name: 'Catalog',
      path: 'catalog',
      component: () => import('@/modules/morefoto/views/CatalogPage.vue'),
      meta: { title: 'Каталог и цены', staffRoles: ['organizer'] }
    },
    {
      name: 'Users',
      path: 'users',
      component: () => import('@/modules/morefoto/views/UsersPage.vue'),
      meta: { title: 'Пользователи', staffRoles: ['organizer'] }
    },
    {
      name: 'GroupConditions',
      path: 'institutions/:institutionId/shoots/:shootId/conditions',
      component: () => import('@/modules/morefoto/views/GroupConditionsPage.vue'),
      meta: { title: 'Условия группы', staffRoles: ['organizer'] }
    },
    {
      name: 'CabinetOverview',
      path: 'overview',
      component: () => import('@/modules/morefoto/views/OverviewPage.vue'),
      meta: { title: 'Личный кабинет' }
    },
    {
      name: 'OrganizationList',
      path: 'institutions',
      component: () => import('@/modules/morefoto/views/OrganizationListPage.vue'),
      meta: {
        title: 'Учреждения',
        staffRoles: isMockApiEnabled ? ['organizer'] : ['organizer', 'curator', 'head']
      }
    },
    {
      name: 'PhotoWorkspace',
      path: 'institutions/:institutionId/shoots/:shootId/photos',
      component: () => import('@/modules/morefoto/views/PhotoWorkspacePage.vue'),
      meta: { title: 'Фотографии съёмки', staffRoles: ['organizer'] }
    },
    {
      name: 'OrganizationShoot',
      path: 'institutions/:institutionId/shoots/:shootId',
      component: () => import('@/modules/morefoto/views/OrganizationShootPage.vue'),
      meta: { title: 'Съёмка', staffRoles: ['organizer'] }
    },
    {
      name: 'CabinetInstitution',
      path: 'institutions/:institutionId',
      component: () => import('@/modules/morefoto/views/InstitutionPage.vue'),
      meta: {
        title: 'Учреждение',
        staffRoles: ['organizer', 'curator', 'head']
      }
    },
    {
      name: 'CabinetProfile',
      path: 'profile',
      component: () => import('@/modules/morefoto/views/ProfilePage.vue'),
      meta: { title: 'Профиль' }
    }
  ]
};
export default MainRoutes;
