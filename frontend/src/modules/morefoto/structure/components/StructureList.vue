<script setup lang="ts">
import type { StructureItem, StructureScope, Group } from '../model';
const props = defineProps<{
  items: StructureItem[];
  scope: StructureScope;
  disabled: boolean;
  canManage: boolean;
  linkGroupsToShoots?: boolean;
}>();
const emit = defineEmits<{ edit: [item: StructureItem] }>();
function destination(item: StructureItem): string | null {
  if (props.scope.kind === 'institution') return '/cabinet/institutions/' + encodeURIComponent(item.id);
  if (props.scope.kind === 'shoot' && props.canManage)
    return '/cabinet/institutions/' + encodeURIComponent(props.scope.institutionId ?? '') + '/shoots/' + encodeURIComponent(item.id);
  if (props.linkGroupsToShoots && 'shootId' in item)
    return '/cabinet/institutions/' + encodeURIComponent(props.scope.institutionId ?? '') + '/shoots/' + encodeURIComponent(item.shootId);
  return null;
}
function description(item: StructureItem): string {
  if ('address' in item) return item.address || 'Адрес не указан';
  if ('date' in item) return item.date ? 'Дата съёмки: ' + item.date.split('-').reverse().join('.') : 'Дата съёмки не назначена';
  return item.groupKind === 'staff' ? 'Группа сотрудников' : 'Обычная группа';
}
const statusLabels = {
  preparing: 'Подготовка',
  open: 'Приём заказов открыт',
  closed: 'Приём заказов закрыт'
};
function deadline(value: string): string {
  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Moscow'
  }).format(new Date(value));
}
function group(item: StructureItem): Group | null {
  return 'groupKind' in item ? item : null;
}
</script>
<template>
  <div v-if="!items.length" class="mf-empty" role="status">Пока нет записей.</div>
  <div v-else class="structure-list">
    <article v-for="item in items" :key="item.id" class="structure-card" data-testid="structure-row" :data-entity-id="item.id">
      <div class="structure-card-main">
        <h2 class="structure-name">
          <RouterLink v-if="destination(item)" :to="destination(item)!">{{ item.name }}</RouterLink
          ><span v-else>{{ item.name }}</span>
        </h2>
        <p class="mf-muted structure-description">{{ description(item) }}</p>
        <template v-if="group(item)">
          <v-chip size="small" variant="tonal" class="mt-3">{{ statusLabels[group(item)!.status] }}</v-chip>
          <p class="mf-muted mt-3">
            {{ group(item)!.teacherId === null ? 'Воспитатель не назначен' : 'Воспитатель назначен' }}
          </p>
          <dl v-if="group(item)!.closesAt" class="structure-calendar">
            <div>
              <dt>Закрытие приёма</dt>
              <dd>{{ deadline(group(item)!.closesAt!) }} МСК</dd>
            </div>
            <div v-if="group(item)!.deliveryDueAt">
              <dt>Срок доставки</dt>
              <dd>{{ deadline(group(item)!.deliveryDueAt!) }} МСК</dd>
            </div>
          </dl>
        </template>
      </div>
      <v-btn
        v-if="canManage"
        variant="outlined"
        :disabled="disabled"
        :aria-label="'Редактировать «' + item.name + '»'"
        @click="emit('edit', item)"
        >Редактировать</v-btn
      >
    </article>
  </div>
</template>
<style scoped>
.structure-list {
  display: grid;
  gap: 16px;
}
.structure-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  border: 1px solid rgba(var(--v-theme-inputBorder), 0.35);
  border-radius: var(--mf-radius-field);
  background: rgb(var(--v-theme-surface));
  padding: 24px;
}
.structure-card-main {
  min-width: 0;
  flex: 1;
}
.structure-name {
  font-size: 20px;
  line-height: 1.4;
  overflow-wrap: anywhere;
}
.structure-name a {
  color: inherit;
  text-decoration-thickness: 1px;
  text-underline-offset: 4px;
}
.structure-description {
  margin-top: 6px;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.structure-calendar {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
  margin-top: 16px;
}
.structure-calendar dt {
  font-size: 12px;
}
.structure-calendar dd {
  margin: 4px 0 0;
}
@media (max-width: 600px) {
  .structure-card {
    flex-direction: column;
    gap: 16px;
    padding: 20px;
  }
  .structure-card > .v-btn {
    align-self: flex-end;
  }
}
</style>
