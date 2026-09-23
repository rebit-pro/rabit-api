<script setup lang="ts">
import type { StructureItem, StructureScope, Group, Institution } from '../model';
import MfStatus from '@/components/status/MfStatus.vue';
import MfEmptyState from '@/components/states/MfEmptyState.vue';
import MfTimeline from '@/components/viz/MfTimeline.vue';
import { countLabel } from '@/components/viz/measures';
import { toneOf } from '@/components/status/tones';
import { groupStateTone } from '../../ui/statusTone';
const props = withDefaults(
  defineProps<{
    items: StructureItem[];
    scope: StructureScope;
    disabled: boolean;
    canManage: boolean;
    linkGroupsToShoots?: boolean;
    emptyTitle?: string;
    emptyText?: string;
    /** Action of the empty list for the organizer; empty — no action. */
    createLabel?: string;
  }>(),
  { linkGroupsToShoots: false, emptyTitle: 'Пока нет записей', emptyText: '', createLabel: '' }
);
const emit = defineEmits<{ edit: [item: StructureItem]; create: [] }>();
// Counting from the page's own clock: the structure answer has no server moment, the state itself comes from the server.
const now = new Date().toISOString();
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
function institution(item: StructureItem): Institution | null {
  return 'address' in item ? item : null;
}
function counters(item: Institution): string | null {
  if (undefined === item.shootCount || undefined === item.groupCount) return null;
  return countLabel(item.shootCount, ['съёмка', 'съёмки', 'съёмок']) + ' · ' + countLabel(item.groupCount, ['группа', 'группы', 'групп']);
}
</script>
<template>
  <MfEmptyState v-if="!items.length" class="mf-panel" :title="emptyTitle" :text="emptyText" icon="mdi-folder-open-outline">
    <v-btn v-if="createLabel" prepend-icon="mdi-plus" :disabled="disabled" @click="emit('create')">{{ createLabel }}</v-btn>
  </MfEmptyState>
  <div v-else class="structure-list">
    <article v-for="item in items" :key="item.id" class="structure-card" data-testid="structure-row" :data-entity-id="item.id">
      <div class="structure-card-main">
        <h2 class="structure-name">
          <RouterLink v-if="destination(item)" :to="destination(item)!">{{ item.name }}</RouterLink
          ><span v-else>{{ item.name }}</span>
        </h2>
        <p class="mf-muted structure-description">{{ description(item) }}</p>
        <div v-if="institution(item)" class="structure-facts">
          <span v-if="counters(institution(item)!)">{{ counters(institution(item)!) }}</span>
          <MfStatus v-if="institution(item)!.openGroupCount" tone="success">Приём открыт: {{ institution(item)!.openGroupCount }}</MfStatus>
          <span v-if="institution(item)!.curatorName">Куратор: {{ institution(item)!.curatorName }}</span>
        </div>
        <template v-if="group(item)">
          <MfStatus :tone="toneOf(groupStateTone, group(item)!.status)" class="mt-3">{{ statusLabels[group(item)!.status] }}</MfStatus>
          <p class="mf-muted mt-3">
            {{ group(item)!.teacherId === null ? 'Ответственный группы не назначен' : 'Ответственный группы назначен' }}
          </p>
          <MfTimeline
            v-if="group(item)!.sentAt"
            class="structure-timeline"
            :sent-at="group(item)!.sentAt"
            :closes-at="group(item)!.closesAt"
            :delivery-at="group(item)!.deliveryDueAt"
            :now="now"
            :timezone="group(item)!.timezone"
          />
          <p v-else-if="group(item)!.closesAt" class="mf-muted mt-3">Закрытие приёма: {{ deadline(group(item)!.closesAt!) }} МСК</p>
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
  gap: var(--mf-space-4);
}
.structure-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--mf-space-6);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface);
  padding: var(--mf-space-6);
}
.structure-card-main {
  min-width: 0;
  flex: 1;
}
.structure-name {
  font-size: var(--mf-text-lg);
  line-height: var(--mf-leading-snug);
  overflow-wrap: anywhere;
}
.structure-name a {
  color: inherit;
  text-decoration-thickness: 1px;
  text-underline-offset: 4px;
}
.structure-description {
  margin-top: var(--mf-space-1);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.structure-facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-2) var(--mf-space-4);
  margin-top: var(--mf-space-3);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.structure-timeline {
  max-width: 560px;
  margin-top: var(--mf-space-4);
}
@media (max-width: 600px) {
  .structure-card {
    flex-direction: column;
    gap: var(--mf-space-4);
    padding: var(--mf-space-5);
  }
  .structure-card > .v-btn {
    align-self: flex-end;
  }
}
</style>
