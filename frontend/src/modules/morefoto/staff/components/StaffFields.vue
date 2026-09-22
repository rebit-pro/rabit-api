<script setup lang="ts">
import { computed } from 'vue';
import { roleLabels } from '../../types';
import type { AssignmentOptions, StaffDraft } from '../model';
const model = defineModel<StaffDraft>({ required: true });
const props = defineProps<{ options: AssignmentOptions; replacements: string[] }>();
const roles = Object.entries(roleLabels).map(([value, title]) => ({ value, title }));
const institutions = computed(() =>
  props.options.institutions.map((item) => ({ value: item.id, title: item.name + (item.address ? ' · ' + item.address : '') }))
);
const groups = computed(() =>
  props.options.groups.map((item) => ({ value: item.id, title: item.institutionName + ' → ' + item.shootName + ' → ' + item.name }))
);
function roleChanged(): void {
  model.value.institutionIds = [];
  model.value.groupIds = [];
  model.value.replaceAssignments = false;
  model.value.reason = '';
}
</script>
<template>
  <v-text-field v-model="model.name" label="Имя сотрудника" aria-label="Имя сотрудника" maxlength="100" />
  <!-- Chromium ignores autocomplete="off" on email fields; an unrecognized token stops it offering the organizer's own addresses. -->
  <v-text-field
    v-model="model.email"
    label="Email сотрудника"
    aria-label="Email сотрудника"
    type="email"
    autocomplete="staff-invite-email"
  />
  <v-select v-model="model.role" :items="roles" label="Роль" aria-label="Роль сотрудника" @update:model-value="roleChanged" />
  <v-checkbox
    v-model="model.active"
    label="Доступ сотрудника включён"
    hint="При отключении текущие сессии завершаются, назначения снимаются."
    persistent-hint
  />
  <section class="mt-5">
    <h3 class="mb-3">Область доступа</h3>
    <p v-if="model.role === 'organizer'" class="mf-muted">Организатор видит все учреждения и управляет сотрудниками.</p>
    <v-autocomplete
      v-else-if="model.role === 'teacher'"
      v-model="model.groupIds"
      :items="groups"
      label="Назначенные группы"
      multiple
      chips
      closable-chips
      clearable
      no-data-text="Группы ещё не созданы"
    />
    <v-autocomplete
      v-else
      v-model="model.institutionIds"
      :items="institutions"
      label="Назначенные учреждения"
      multiple
      chips
      closable-chips
      clearable
      no-data-text="Учреждения ещё не созданы"
    />
    <p v-if="model.role !== 'organizer'" class="mf-muted">Без назначений сотрудник войдёт, но увидит пустую рабочую область.</p>
  </section>
  <v-alert v-if="replacements.length" type="warning" variant="tonal" class="mt-5">
    Вы заменяете: {{ replacements.join(', ') }}. Их прежние сессии будут завершены.
  </v-alert>
  <v-checkbox
    v-if="replacements.length"
    v-model="model.replaceAssignments"
    label="Подтверждаю замену текущих ответственных"
    aria-label="Подтвердить замену ответственных"
  />
  <v-textarea
    v-if="replacements.length"
    v-model="model.reason"
    label="Причина замены"
    aria-label="Причина замены"
    maxlength="500"
    rows="2"
    auto-grow
  />
  <v-alert type="info" variant="tonal" class="mt-5">
    Новый сотрудник получит статус «Ожидает регистрации» и сам задаст пароль через обычную регистрацию. Пароль здесь не создаётся и не
    показывается.
  </v-alert>
</template>
