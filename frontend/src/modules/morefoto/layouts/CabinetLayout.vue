<script setup lang="ts">
import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { organizationChangedEvent } from '../organization/repository';
import { useRoute, useRouter } from 'vue-router';
import { useDisplay } from 'vuetify';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { isStaffRole, roleLabels } from '../types';
import { cabinetNavigation } from './navigation';
import { useNavigationCounters } from './useNavigationCounters';
import MfLogo from '@/components/brand/MfLogo.vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import { avatarSeed } from '@/components/avatar/avatar';
import MfUserMenu from '@/components/shell/MfUserMenu.vue';

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
const scrolled = shallowRef(false);
const roleLabel = computed(() => (isStaffRole(auth.user?.role) ? roleLabels[auth.user.role] : 'Участник'));
const userName = computed(() => auth.user?.name?.trim() || auth.user?.email || 'Сотрудник');
const userSeed = computed(() => avatarSeed(auth.user?.id, auth.user?.email));
const sectionTitle = computed(() => String(route.meta.title ?? ''));
const staffRole = computed(() => (isStaffRole(auth.user?.role) ? auth.user.role : null));
const counters = useNavigationCounters(() => staffRole.value);
const countId = (path: string) => 'nav-count' + path.replace(/\//g, '-');
const navigation = computed(() =>
  cabinetNavigation({
    role: staffRole.value,
    permissions: auth.user?.permissions ?? [],
    demo: isMockApiEnabled,
    counters: counters.value
  })
);
// The app bar gets its shadow only after the page has scrolled (design plan 7.4).
function trackScroll() {
  scrolled.value = window.scrollY > 0;
}
onMounted(() => window.addEventListener('scroll', trackScroll, { passive: true }));
onScopeDispose(() => window.removeEventListener('scroll', trackScroll));
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
    <v-app-bar flat color="surface" :height="mdAndUp ? 64 : 56" class="mf-appbar" :class="{ 'mf-appbar--scrolled': scrolled }">
      <v-app-bar-nav-icon v-if="!mdAndUp" aria-label="Открыть меню" class="mf-appbar__menu" @click="drawer = !drawer" />
      <RouterLink :to="auth.homePath" class="mf-appbar__brand">
        <MfLogo :variant="mdAndUp ? 'horizontal' : 'compact'" :size="mdAndUp ? 28 : 24" />
      </RouterLink>
      <span v-if="!mdAndUp && sectionTitle" class="mf-appbar__title">{{ sectionTitle }}</span>
      <v-spacer />
      <span v-if="mdAndUp" class="mf-appbar__role">{{ roleLabel }}</span>
      <MfUserMenu
        :name="userName"
        :email="auth.user?.email ?? ''"
        :seed="userSeed"
        :role-label="roleLabel"
        :avatar-src="auth.user?.avatar?.thumbUrl"
        profile-to="/cabinet/profile"
        class="mf-appbar__user"
        @logout="auth.logout()"
      />
    </v-app-bar>
    <v-navigation-drawer v-model="drawer" :permanent="mdAndUp" :temporary="!mdAndUp" width="248" class="mf-sidebar">
      <RouterLink to="/cabinet/profile" class="mf-sidebar__user" :aria-label="'Профиль: ' + userName">
        <MfAvatar :seed="userSeed" :name="userName" :email="auth.user?.email" :size="40" :src="auth.user?.avatar?.thumbUrl" decorative />
        <span class="mf-sidebar__who">
          <strong>{{ userName }}</strong>
          <span>{{ roleLabel }}</span>
        </span>
      </RouterLink>
      <nav aria-label="Основная навигация" class="mf-sidebar__nav">
        <section v-for="group in navigation" :key="group.title" class="mf-sidebar__group" :aria-label="group.title">
          <p class="mf-sidebar__caption" aria-hidden="true">{{ group.title }}</p>
          <v-list nav density="compact" class="mf-sidebar__list">
            <v-list-item
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              :title="item.title"
              :prepend-icon="item.icon"
              :aria-describedby="item.count ? countId(item.to) : undefined"
              class="mf-navigation-item"
            >
              <!-- The badge stays out of the link name, so the section keeps its plain name for assistive tech. -->
              <template v-if="item.count" #append>
                <span class="mf-navigation-count" aria-hidden="true">{{ item.count }}</span>
                <span :id="countId(item.to)" hidden>{{ item.count }} ждут вашего внимания</span>
              </template>
            </v-list-item>
          </v-list>
        </section>
      </nav>
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
/* The divider is drawn inside the bar, so the bar keeps its 64/56 px height (design plan 10.3). */
.mf-appbar {
  box-shadow: inset 0 -1px 0 var(--mf-color-border) !important;
  transition: box-shadow var(--mf-duration-fast) var(--mf-ease-standard);
}
.mf-appbar--scrolled {
  box-shadow:
    inset 0 -1px 0 var(--mf-color-border),
    var(--mf-shadow-sm) !important;
}
.mf-appbar :deep(.v-toolbar__content) {
  gap: var(--mf-space-3);
  padding-inline: var(--mf-space-4) var(--mf-space-3);
}
.mf-appbar__brand {
  display: inline-flex;
  align-items: center;
  min-height: var(--mf-touch-size);
  border-radius: var(--mf-radius-sm);
  text-decoration: none;
}
.mf-appbar__title {
  min-width: 0;
  overflow: hidden;
  font-size: var(--mf-text-base);
  font-weight: var(--mf-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mf-appbar__role {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-medium);
}
.mf-sidebar :deep(.v-navigation-drawer__content) {
  display: flex;
  flex-direction: column;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4) var(--mf-space-3);
}
.mf-sidebar__user {
  display: flex;
  gap: var(--mf-space-3);
  align-items: center;
  padding: var(--mf-space-2);
  border-radius: var(--mf-radius-md);
  color: var(--mf-color-text);
  text-decoration: none;
  transition: background-color var(--mf-duration-fast) var(--mf-ease-standard);
}
.mf-sidebar__user:hover {
  background: var(--mf-color-nav-hover);
}
.mf-sidebar__who {
  display: grid;
  min-width: 0;
  font-size: var(--mf-text-md);
  line-height: var(--mf-leading-snug);
}
.mf-sidebar__who strong {
  overflow: hidden;
  font-weight: var(--mf-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mf-sidebar__who span {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.mf-sidebar__caption {
  padding: var(--mf-space-4) var(--mf-space-3) var(--mf-space-1);
  color: var(--mf-color-text-tertiary);
  font-size: var(--mf-text-xs);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-caps);
  text-transform: uppercase;
}
.mf-sidebar__list {
  padding: 0;
  background: transparent;
}
.mf-navigation-count {
  min-width: 22px;
  padding: 1px var(--mf-space-2);
  border-radius: var(--mf-radius-full);
  background: var(--mf-tone-warning-bg);
  color: var(--mf-tone-warning-fg);
  font-size: var(--mf-text-xs);
  font-variant-numeric: tabular-nums lining-nums;
  font-weight: var(--mf-weight-semibold);
  line-height: 18px;
  text-align: center;
}
.mf-navigation-item {
  position: relative;
  min-height: var(--mf-touch-size);
  margin-bottom: 2px;
  border-radius: var(--mf-radius-sm);
  color: var(--mf-color-nav-fg);
}
.mf-navigation-item :deep(.v-list-item-title) {
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-medium);
  line-height: var(--mf-leading-snug);
  white-space: normal;
}
.mf-navigation-item :deep(.v-list-item__prepend > .v-icon) {
  margin-inline-end: var(--mf-space-3);
  opacity: 1;
  font-size: 20px;
}
.mf-navigation-item :deep(.v-list-item__spacer) {
  display: none;
}
.mf-navigation-item.v-list-item--active {
  background: var(--mf-color-nav-active-bg);
  color: var(--mf-color-nav-active-fg);
}
.mf-navigation-item.v-list-item--active :deep(.v-list-item__overlay) {
  opacity: 0;
}
.mf-navigation-item.v-list-item--active::before {
  content: '';
  position: absolute;
  inset-block: 10px;
  left: 0;
  width: 3px;
  border-radius: 0 var(--mf-radius-xs) var(--mf-radius-xs) 0;
  background: var(--mf-color-primary);
}
</style>
