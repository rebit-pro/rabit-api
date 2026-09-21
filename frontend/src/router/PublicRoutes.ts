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
      meta: { title: 'Оформление заказа', demoOnly: true }
    },
    {
      name: 'Order',
      path: '/orders/access/:orderKey',
      component: () => import('@/modules/morefoto/views/OrderPage.vue'),
      meta: { title: 'Ваш заказ', demoOnly: true }
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
        description: 'Фотографии вашей группы в MoreFoto.'
      }
    },
    { path: '/', redirect: '/login' },
    { path: '/documentation', redirect: '/login' },
    { path: '/register', redirect: '/login' },
    { path: '/dashboard', redirect: '/cabinet/overview' },
    { path: '/main', redirect: '/cabinet/overview' },
    {
      name: 'Login',
      path: '/login',
      component: () => import('@/views/authentication/LoginPage.vue'),
      meta: { title: 'Вход', description: 'Вход в личный кабинет MoreFoto.' }
    },
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
export default PublicRoutes;
