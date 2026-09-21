<script setup lang="ts">
import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { organizationChangedEvent } from '../organization/repository';
import { useRoute, useRouter } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { isStaffRole, roleLabels } from '../types';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
function refreshAccess() {
  if (isMockApiEnabled) {
    auth.restoreSession();
    if (!auth.isAuthenticated) void router.replace('/login?reason=session-expired');
  }
}
function storageAccess(event: StorageEvent) {
  if (event.key === 'morefoto:demo:organization:v1') refreshAccess();
}
onMounted(() => {
  window.addEventListener(organizationChangedEvent, refreshAccess);
  window.addEventListener('storage', storageAccess);
});
onScopeDispose(() => {
  window.removeEventListener(organizationChangedEvent, refreshAccess);
  window.removeEventListener('storage', storageAccess);
});
const { mdAndUp } = useDisplay();
const drawer = shallowRef(false);
const roleLabel = computed(() => (isStaffRole(auth.user?.role) ? roleLabels[auth.user.role] : 'Участник'));
const navigation = computed(() =>
  !isMockApiEnabled
    ? [
        ...(['organizer', 'curator', 'head'].includes(auth.user?.role ?? '')
          ? [
              {
                title: 'Учреждения',
                to: '/cabinet/institutions',
                icon: 'mdi-home-city-outline'
              }
            ]
          : []),
        ...(auth.user?.permissions?.includes('catalog.manage')
          ? [
              {
                title: 'Каталог и цены',
                to: '/cabinet/catalog',
                icon: 'mdi-tag-outline'
              }
            ]
          : []),
        ...(auth.user?.permissions?.includes('staff.manage')
          ? [
              {
                title: 'Сотрудники',
                to: '/cabinet/users',
                icon: 'mdi-account-group-outline'
              }
            ]
          : []),
        ...(['organizer', 'curator', 'teacher'].includes(auth.user?.role ?? '')
          ? [
              {
                title: 'Заявки на списки сотрудников',
                to: '/cabinet/staff-requests',
                icon: 'mdi-account-check-outline'
              }
            ]
          : []),
        {
          title: 'Профиль',
          to: '/cabinet/profile',
          icon: 'mdi-account-outline'
        }
      ]
    : [
        {
          title: auth.user?.role === 'teacher' ? 'Мои группы' : 'Обзор',
          to: '/cabinet/overview',
          icon: 'mdi-view-dashboard-outline'
        },
        ...(auth.user?.role === 'organizer'
          ? [
              {
                title: 'Учреждения',
                to: '/cabinet/institutions',
                icon: 'mdi-home-city-outline'
              },
              {
                title: 'Каталог и цены',
                to: '/cabinet/catalog',
                icon: 'mdi-tag-outline'
              },
              {
                title: 'Пользователи',
                to: '/cabinet/users',
                icon: 'mdi-account-group-outline'
              }
            ]
          : []),
        ...(['organizer', 'curator'].includes(auth.user?.role ?? '')
          ? [
              {
                title: 'Производство',
                to: '/cabinet/production',
                icon: 'mdi-printer-outline'
              },
              {
                title: 'Заказы',
                to: '/cabinet/orders',
                icon: 'mdi-receipt-text-outline'
              },
              {
                title: 'Обращения',
                to: '/cabinet/support',
                icon: 'mdi-message-text-outline'
              }
            ]
          : []),
        {
          title: 'Доставка',
          to: '/cabinet/delivery',
          icon: 'mdi-truck-delivery-outline'
        },
        {
          title: 'Ссылки и сроки',
          to: '/cabinet/links',
          icon: 'mdi-link-variant'
        },
        ...(auth.user?.role !== 'head'
          ? [
              {
                title: 'Заявки на списки сотрудников',
                to: '/cabinet/staff-requests',
                icon: 'mdi-account-check-outline'
              }
            ]
          : []),
        {
          title: 'Профиль',
          to: '/cabinet/profile',
          icon: 'mdi-account-outline'
        }
      ]
);
watch(
  mdAndUp,
  (value) => {
    drawer.value = value;
  },
  { immediate: true }
);
watch(
  () => route.fullPath,
  () => {
    if (!mdAndUp.value) drawer.value = false;
  }
);
</script>

<template>
  <v-app theme="MoreFotoTheme" class="morefoto-app">
    <a href="#cabinet-main" class="mf-skip">Перейти к содержимому</a>
    <v-app-bar flat border="b" color="surface" height="72">
      <v-app-bar-nav-icon v-if="!mdAndUp" aria-label="Открыть меню" @click="drawer = !drawer" />
      <v-app-bar-title>
        <RouterLink :to="auth.homePath" class="mf-brand">Море<span>фото</span></RouterLink>
      </v-app-bar-title>
      <span class="mf-role-label">{{ roleLabel }}</span>
      <v-btn icon="mdi-logout" aria-label="Выйти" variant="text" color="secondary" class="mr-2" @click="auth.logout()" />
    </v-app-bar>
    <v-navigation-drawer v-model="drawer" :permanent="mdAndUp" :temporary="!mdAndUp" width="248">
      <div class="mf-sidebar-caption">ЛИЧНЫЙ КАБИНЕТ</div>
      <v-list nav aria-label="Основная навигация">
        <v-list-item
          v-for="item in navigation"
          :key="item.to"
          :to="item.to"
          :title="item.title"
          :prepend-icon="item.icon"
          class="mf-navigation-item"
          color="primary"
        />
      </v-list>
      <template #append>
        <div class="mf-sidebar-user">
          <strong>{{ auth.user?.name }}</strong>
          <span>{{ auth.user?.email }}</span>
        </div>
      </template>
    </v-navigation-drawer>
    <v-main>
      <main id="cabinet-main" class="mf-main" tabindex="-1">
        <div v-if="isMockApiEnabled" class="mf-demo-note" role="note">
          <v-icon icon="mdi-flask-outline" size="18" />
          Демонстрационная версия · данные вымышленные
        </div>
        <RouterView />
      </main>
    </v-main>
  </v-app>
</template>

<style scoped>
.mf-navigation-item :deep(.v-list-item-title) {
  white-space: normal;
  line-height: 1.35;
}
</style>
