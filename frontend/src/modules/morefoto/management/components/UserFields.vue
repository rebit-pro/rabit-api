<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import type { UserCommand, ManagementErrors } from '../types';
import type { OrganizationSnapshot } from '../../organization/types';
import { roleLabels } from '../../types';
import { replacementNames } from '../rules';
const model = defineModel<UserCommand>({ required: true });
const props = defineProps<{ errors: ManagementErrors; state: OrganizationSnapshot; actorId: number }>();
const roles = Object.entries(roleLabels).map(([value, title]) => ({ value, title }));
const replacements = computed(() => replacementNames(model.value, props.state));
const shoot = shallowRef<string | null>(null);
const shoots = computed(() =>
  props.state.shoots.map((x) => ({
    value: x.id,
    title: (props.state.institutions.find((i) => i.id === x.institutionId)?.name ?? '') + ' → ' + x.name
  }))
);
const groups = computed(() =>
  props.state.groups.map((x) => ({
    value: x.id,
    title: (props.state.institutions.find((i) => i.id === x.institutionId)?.name ?? '') + ' → ' + x.shootName + ' → ' + x.name
  }))
);
function selectShoot() {
  if (shoot.value)
    model.value.groupIds = [
      ...new Set([...model.value.groupIds, ...props.state.groups.filter((x) => x.shootId === shoot.value).map((x) => x.id)])
    ];
}
function roleChanged() {
  model.value.institutionIds = [];
  model.value.groupIds = [];
  model.value.replaceAssignments = false;
}
</script>
<template>
  <v-text-field
    v-model="model.name"
    label="Имя пользователя"
    aria-label="Имя пользователя"
    maxlength="100"
    :error-messages="errors.name"
    :aria-invalid="!!errors.name"
  />
  <v-text-field
    v-model="model.email"
    label="Email пользователя"
    aria-label="Email пользователя"
    type="email"
    autocomplete="off"
    :error-messages="errors.email"
    :aria-invalid="!!errors.email"
  />
  <v-select
    v-model="model.role"
    :items="roles"
    label="Роль пользователя"
    aria-label="Роль пользователя"
    :disabled="model.id === actorId"
    :error-messages="errors.role"
    :aria-invalid="!!errors.role"
    @update:model-value="roleChanged"
  />
  <v-checkbox
    v-model="model.active"
    label="Активный пользователь"
    aria-label="Активный пользователь"
    :disabled="model.id === actorId"
    hint="Отключение прекращает доступ и снимает назначения. История сохраняется."
    persistent-hint
  />
  <section class="mt-6">
    <h3 class="mb-3">Область доступа</h3>
    <p v-if="model.role === 'organizer'" class="mf-muted">Все учреждения, съёмки и группы. Управление каталогом и пользователями.</p>
    <template v-else-if="model.role === 'teacher'">
      <v-autocomplete
        v-model="shoot"
        :items="shoots"
        label="Съёмка для назначения групп"
        aria-label="Съёмка для назначения групп"
        clearable
      />
      <v-btn variant="outlined" :disabled="!shoot" class="mb-5" @click="selectShoot">Добавить группы съёмки</v-btn>
      <v-autocomplete
        v-model="model.groupIds"
        :items="groups"
        label="Назначенные группы"
        aria-label="Назначенные группы"
        multiple
        chips
        closable-chips
        clearable
        :error-messages="errors.scope"
        :aria-invalid="!!errors.scope"
      />
      <ul v-if="model.groupIds.length" class="scope-selections">
        <li v-for="id in model.groupIds" :key="id">{{ groups.find((g) => g.value === id)?.title }}</li>
      </ul>
      <p class="mf-muted">Доступ только к выбранным группам. Индивидуальные заказы и контакты покупателей не показываются.</p>
    </template>
    <template v-else>
      <v-autocomplete
        v-model="model.institutionIds"
        :items="state.institutions"
        item-title="name"
        item-value="id"
        label="Назначенные учреждения"
        aria-label="Назначенные учреждения"
        multiple
        chips
        closable-chips
        clearable
        :error-messages="errors.scope"
        :aria-invalid="!!errors.scope"
      />
      <p class="mf-muted">Назначение охватывает все съёмки и группы учреждения. Руководителю не показываются индивидуальные заказы.</p>
    </template>
    <p v-if="model.role !== 'organizer'" class="mf-muted mt-3">Без назначений сотрудник увидит пустую область доступа.</p>
    <v-alert v-if="replacements.length" type="warning" variant="tonal" class="mt-5"
      >На выбранных местах уже назначены: {{ replacements.join(', ') }}. Их доступ к этим местам будет снят.</v-alert
    >
    <v-checkbox
      v-if="replacements.length"
      v-model="model.replaceAssignments"
      label="Заменить текущих ответственных"
      aria-label="Заменить текущих ответственных"
      :error-messages="errors.scope"
      :aria-invalid="!!errors.scope"
    />
  </section>
  <v-alert type="info" variant="tonal" class="mt-6"
    >Демонстрационная учётная запись хранится в этом браузере. Вход по указанному email и паролю morefoto-demo. Письмо не отправляется.
    После изменения email, роли или активности потребуется новый вход.</v-alert
  >
</template>

<style scoped>
.scope-selections {
  padding-left: 20px;
  margin: 0 0 20px;
  overflow-wrap: anywhere;
}
.scope-selections li + li {
  margin-top: 8px;
}
</style>
