import type { RouteRecordRaw } from 'vue-router';
import { isMockApiEnabled } from '@/mocks/config';

const PublicRoutes: RouteRecordRaw = {
  path: '/',
  component: () => import('@/layouts/blank/BlankLayout.vue'),
  children: [
    ...(isMockApiEnabled
      ? [
          {
            name: 'Review',
            path: '/demo/review',
            component: () => import('@/modules/morefoto/views/ReviewPage.vue'),
            meta: { title: 'Сквозная проверка MoreFoto' }
          },
          {
            name: 'UiExamples',
            path: '/demo/ui',
            component: () => import('@/modules/morefoto/views/UiExamplesPage.vue'),
            meta: { title: 'Поля и кнопки' }
          }
        ]
      : []),
    {
      name: 'Payment',
      path: '/orders/access/:orderKey/payment',
      component: () => import('@/modules/morefoto/views/PaymentPage.vue'),
      meta: { title: 'Демонстрационная оплата', demoOnly: true }
    },
    {
      name: 'Checkout',
      path: '/g/:token/checkout',
      component: () => import('@/modules/morefoto/views/CheckoutPage.vue'),
      meta: { title: 'Оформление заказа' }
    },
    {
      name: 'Order',
      path: '/orders/access/:orderKey',
      component: () => import('@/modules/morefoto/views/OrderPage.vue'),
      meta: { title: 'Ваш заказ' }
    },
    {
      name: 'Cart',
      path: '/g/:token/cart',
      component: () => import('@/modules/morefoto/views/CartPage.vue'),
      meta: { title: 'Корзина' }
    },
    {
      name: 'Gallery',
      path: '/g/:token',
      component: () => import('@/modules/morefoto/views/GalleryPage.vue'),
      meta: {
        title: 'Галерея фотографий',
        description: 'Фотографии вашей группы в «Море фото».'
      }
    },
    { path: '/', redirect: '/login' },
    { path: '/documentation', redirect: '/login' },
    { path: '/register', redirect: '/login' },
    { path: '/dashboard', redirect: '/cabinet/overview' },
    { path: '/main', redirect: '/cabinet/overview' },
    {
      name: 'AccessInvite',
      path: '/access/invite/:token',
      component: () => import('@/views/authentication/InvitePage.vue'),
      meta: { title: 'Приглашение в кабинет' }
    },
    {
      name: 'AccessRecover',
      path: '/access/recover',
      component: () => import('@/views/authentication/RecoverPage.vue'),
      meta: { title: 'Восстановление доступа' }
    },
    {
      name: 'AccessReset',
      path: '/access/reset/:token',
      component: () => import('@/views/authentication/ResetPage.vue'),
      meta: { title: 'Новый пароль' }
    },
    {
      name: 'Login',
      path: '/login',
      component: () => import('@/views/authentication/LoginPage.vue'),
      meta: { title: 'Вход', description: 'Вход в личный кабинет «Море фото».' }
    }
  ]
};
export default PublicRoutes;

/** Service pages: inside the cabinet shell for signed-in staff, standalone otherwise. */
export const ServiceRoutes: RouteRecordRaw = {
  path: '/',
  component: () => import('@/layouts/AuthAwareLayout.vue'),
  children: [
    {
      name: 'AccessUnavailable',
      path: '/access-unavailable',
      component: () => import('@/modules/morefoto/views/AccessPage.vue'),
      meta: { title: 'Доступ ограничен', requiresAuth: true }
    },
    {
      name: 'FeatureUnavailable',
      path: '/feature-unavailable',
      component: () => import('@/modules/morefoto/views/FeatureUnavailablePage.vue'),
      meta: { title: 'Раздел пока недоступен' }
    },
    {
      name: 'NotFound',
      path: '/:pathMatch(.*)*',
      component: () => import('@/modules/morefoto/views/NotFoundPage.vue'),
      meta: { title: 'Страница не найдена' }
    }
  ]
};
