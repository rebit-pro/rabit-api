<script setup lang="ts">
import MfAvatar from '../avatar/MfAvatar.vue';

defineProps<{ name: string; email: string; seed: string; roleLabel: string; profileTo: string }>();
defineEmits<{ logout: [] }>();
</script>

<template>
  <v-menu location="bottom end" :offset="8" content-class="morefoto-app mf-ui-overlay">
    <template #activator="{ props: activator }">
      <v-btn v-bind="activator" variant="text" color="secondary" class="mf-user-menu" aria-label="Меню пользователя">
        <MfAvatar :seed="seed" :name="name" :email="email" :size="32" decorative />
        <v-icon icon="mdi-chevron-down" size="18" class="mf-user-menu__chevron" />
      </v-btn>
    </template>
    <div class="mf-user-menu__panel">
      <div class="mf-user-menu__who">
        <MfAvatar :seed="seed" :name="name" :email="email" :size="40" decorative />
        <div class="mf-user-menu__text">
          <strong>{{ name }}</strong>
          <span>{{ email }}</span>
          <span class="mf-user-menu__role">{{ roleLabel }}</span>
        </div>
      </div>
      <v-btn :to="profileTo" variant="text" color="secondary" block prepend-icon="mdi-account-outline" class="mf-user-menu__action"
        >Профиль</v-btn
      >
      <v-btn variant="text" color="secondary" block prepend-icon="mdi-logout" class="mf-user-menu__action" @click="$emit('logout')"
        >Выйти</v-btn
      >
    </div>
  </v-menu>
</template>

<style>
.mf-user-menu.v-btn {
  gap: var(--mf-space-1);
  padding-inline: var(--mf-space-1) var(--mf-space-2);
  border-radius: var(--mf-radius-full);
}
.mf-user-menu__chevron {
  color: var(--mf-color-text-secondary);
}
.mf-user-menu__panel {
  display: grid;
  gap: var(--mf-space-1);
  min-width: 264px;
  max-width: min(320px, calc(100vw - 32px));
  padding: var(--mf-space-2);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface);
  box-shadow: var(--mf-shadow-md);
}
.mf-user-menu__who {
  display: flex;
  gap: var(--mf-space-3);
  align-items: center;
  padding: var(--mf-space-2) var(--mf-space-2) var(--mf-space-3);
  margin-bottom: var(--mf-space-1);
  border-bottom: 1px solid var(--mf-color-divider);
}
.mf-user-menu__text {
  display: grid;
  gap: 2px;
  min-width: 0;
  font-size: var(--mf-text-md);
  line-height: var(--mf-leading-snug);
  overflow-wrap: anywhere;
}
.mf-user-menu__text span {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.mf-user-menu__role {
  color: var(--mf-color-primary) !important;
  font-weight: var(--mf-weight-medium);
}
.morefoto-app .mf-user-menu__action.v-btn {
  justify-content: start;
  min-height: var(--mf-touch-size);
  border-radius: var(--mf-radius-sm);
}
</style>
