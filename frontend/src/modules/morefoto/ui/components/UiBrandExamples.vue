<script setup lang="ts">
import MfLogo from '@/components/brand/MfLogo.vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import MfAvatarStack from '@/components/avatar/MfAvatarStack.vue';
import { avatarSeed } from '@/components/avatar/avatar';

const sizes = [24, 32, 40, 56, 96] as const;
const people = [
  { id: 1, name: 'Иванова Мария Сергеевна' },
  { id: 2, name: 'Анна-Мария Петрова' },
  { id: 3, name: 'Олег Смирнов' },
  { id: 4, name: 'Рита Кузнецова' },
  { id: 5, name: 'Юлия Ёлкина' },
  { id: 6, name: 'Павел Орлов' }
].map((person) => ({ seed: avatarSeed(person.id), name: person.name }));
const states = [
  { label: 'Активен', status: null },
  { label: 'Ожидает регистрации', status: 'pending' },
  { label: 'Приглашение истекло', status: 'expired' },
  { label: 'Доступ отключён', status: 'blocked' }
] as const;
</script>

<template>
  <section id="ui-brand" class="mf-panel ui-example-section" aria-labelledby="ui-brand-title">
    <div>
      <h2 id="ui-brand-title">Бренд</h2>
      <p class="mf-muted">Знак «волна сквозь кадр», лок-апы логотипа и аватарки сотрудников с буквами вместо фото.</p>
    </div>
    <div class="ui-brand-row">
      <figure class="ui-brand-cell">
        <MfLogo :size="28" />
        <figcaption>horizontal · шапка</figcaption>
      </figure>
      <figure class="ui-brand-cell">
        <MfLogo variant="compact" :size="24" />
        <figcaption>compact · mobile</figcaption>
      </figure>
      <figure class="ui-brand-cell">
        <MfLogo variant="stacked" :size="48" />
        <figcaption>stacked · вход</figcaption>
      </figure>
      <figure class="ui-brand-cell">
        <MfLogo :size="24" mono />
        <figcaption>mono · галерея</figcaption>
      </figure>
      <figure class="ui-brand-cell ui-brand-cell--inverse">
        <MfLogo :size="28" mono />
        <figcaption>mono на тёмном</figcaption>
      </figure>
    </div>
    <h3 class="ui-token-heading">Размеры</h3>
    <div class="ui-brand-row ui-brand-row--baseline">
      <MfAvatar v-for="size in sizes" :key="size" :seed="people[0]!.seed" :name="people[0]!.name" :size="size" />
    </div>
    <h3 class="ui-token-heading">Состояния</h3>
    <div class="ui-brand-row">
      <figure v-for="(state, index) in states" :key="state.label" class="ui-brand-cell">
        <MfAvatar :seed="people[index]!.seed" :name="people[index]!.name" :size="56" :status="state.status" />
        <figcaption>{{ state.label }}</figcaption>
      </figure>
      <figure class="ui-brand-cell">
        <MfAvatar :seed="people[4]!.seed" :name="people[4]!.name" :size="56" you />
        <figcaption>Это вы</figcaption>
      </figure>
    </div>
    <h3 class="ui-token-heading">Стопка и палитра</h3>
    <div class="ui-brand-row">
      <MfAvatarStack :people="people" />
      <MfAvatar v-for="person in people" :key="person.seed" :seed="person.seed" :name="person.name" :size="40" />
      <MfAvatar seed="email:ivan@example.invalid" email="ivan@example.invalid" :size="40" />
      <MfAvatar seed="email:" :size="40" />
    </div>
  </section>
</template>

<style scoped>
.ui-token-heading {
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
  margin-top: var(--mf-space-2);
}
.ui-brand-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-6);
  align-items: center;
}
.ui-brand-row--baseline {
  align-items: flex-end;
}
.ui-brand-cell {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-2);
  margin: 0;
  padding: var(--mf-space-4);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface);
}
.ui-brand-cell figcaption {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.ui-brand-cell--inverse {
  background: var(--mf-color-surface-inverse);
  color: var(--mf-color-text-inverse);
}
.ui-brand-cell--inverse :deep(.mf-logo) {
  color: var(--mf-color-text-inverse);
}
.ui-brand-cell--inverse figcaption {
  color: var(--mf-color-text-inverse);
}
</style>
