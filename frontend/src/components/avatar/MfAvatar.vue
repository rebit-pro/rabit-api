<script setup lang="ts">
import { computed } from 'vue';
import { AVATAR_NEUTRAL_TONE, avatarInitials, avatarTone } from './avatar';

type AvatarStatus = 'pending' | 'expired' | 'blocked';

const props = withDefaults(
  defineProps<{
    seed: string;
    name?: string | null;
    email?: string | null;
    size?: 24 | 32 | 40 | 56 | 96;
    status?: AvatarStatus | null;
    you?: boolean;
    /** Next to a visible name the avatar is decoration and stays out of the accessibility tree. */
    decorative?: boolean;
  }>(),
  { name: null, email: null, size: 32, status: null, you: false, decorative: false }
);

const statusText: Record<AvatarStatus, string> = {
  pending: 'ожидает регистрации',
  expired: 'приглашение истекло',
  blocked: 'доступ отключён'
};
const badgeIcon: Record<AvatarStatus, string> = {
  pending: 'mdi-clock-outline',
  expired: 'mdi-clock-alert-outline',
  blocked: 'mdi-lock'
};

const letters = computed(() => {
  const initials = avatarInitials(props.name, props.email);
  return 24 === props.size ? Array.from(initials)[0]! : initials;
});
const tone = computed(() => ('blocked' === props.status ? AVATAR_NEUTRAL_TONE : avatarTone(props.seed)));
const label = computed(() => {
  const who = props.name?.trim() || props.email?.trim() || 'Сотрудник';
  return props.status ? `${who}, ${statusText[props.status]}` : who;
});
</script>

<template>
  <span
    class="mf-avatar"
    :class="[`mf-avatar--${size}`, status ? `mf-avatar--${status}` : null, { 'mf-avatar--you': you }]"
    :style="{ '--mf-avatar-bg': `var(--mf-avatar-${tone}-bg)`, '--mf-avatar-fg': `var(--mf-avatar-${tone}-fg)` }"
    :role="decorative ? undefined : 'img'"
    :aria-label="decorative ? undefined : label"
    :aria-hidden="decorative ? 'true' : undefined"
  >
    <span class="mf-avatar__letters" aria-hidden="true">{{ letters }}</span>
    <span v-if="status && size >= 40" class="mf-avatar__badge" aria-hidden="true">
      <v-icon :icon="badgeIcon[status]" />
    </span>
  </span>
</template>

<style scoped>
.mf-avatar {
  --mf-avatar-size: 32px;
  position: relative;
  display: inline-grid;
  flex: 0 0 auto;
  place-items: center;
  width: var(--mf-avatar-size);
  height: var(--mf-avatar-size);
  border-radius: var(--mf-radius-full);
  background: var(--mf-avatar-bg);
  color: var(--mf-avatar-fg);
  font-family: var(--mf-font-sans);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: 0.02em;
  line-height: 1;
  user-select: none;
}
.mf-avatar--24 {
  --mf-avatar-size: 24px;
  font-size: 0.625rem;
}
.mf-avatar--32 {
  --mf-avatar-size: 32px;
  font-size: 0.75rem;
}
.mf-avatar--40 {
  --mf-avatar-size: 40px;
  font-size: 0.875rem;
}
.mf-avatar--56 {
  --mf-avatar-size: 56px;
  font-size: 1.25rem;
}
.mf-avatar--96 {
  --mf-avatar-size: 96px;
  font-size: 2.125rem;
}
.mf-avatar--pending,
.mf-avatar--expired {
  background: color-mix(in srgb, var(--mf-avatar-bg) 70%, transparent);
  outline: 1.5px dashed var(--mf-tone-pending-fg);
  outline-offset: 1px;
}
.mf-avatar--you {
  box-shadow:
    var(--mf-shadow-ring-surface),
    0 0 0 4px var(--mf-color-primary);
}
.mf-avatar__badge {
  position: absolute;
  right: -2px;
  bottom: -2px;
  display: grid;
  place-items: center;
  width: calc(var(--mf-avatar-size) * 0.3);
  height: calc(var(--mf-avatar-size) * 0.3);
  min-width: 14px;
  min-height: 14px;
  border-radius: var(--mf-radius-full);
  box-shadow: var(--mf-shadow-ring-surface);
  font-size: 10px;
}
.mf-avatar__badge :deep(.v-icon) {
  font-size: inherit;
}
.mf-avatar--pending .mf-avatar__badge {
  background: var(--mf-tone-pending-bg);
  color: var(--mf-tone-pending-fg);
}
.mf-avatar--expired .mf-avatar__badge {
  background: var(--mf-tone-warning-bg);
  color: var(--mf-tone-warning-fg);
}
.mf-avatar--blocked .mf-avatar__badge {
  background: var(--mf-tone-danger-bg);
  color: var(--mf-tone-danger-fg);
}
</style>
