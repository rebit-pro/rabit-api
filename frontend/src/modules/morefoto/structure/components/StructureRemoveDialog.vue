<script setup lang="ts">
import { computed } from 'vue';
import UiRemoveDialog from '../../ui/components/UiRemoveDialog.vue';
import type { StructureKind } from '../model';
/** What goes away with an institution, a shoot or a group (#92): the server refuses records that already have orders. */
const props = defineProps<{ kind: StructureKind | null; names: string[]; busy: boolean }>();
defineEmits<{ confirm: []; close: [] }>();
const many = 'ссылки для родителей и их история. Переданные родителям ссылки перестанут открываться';
const words: Record<StructureKind, { one: string; many: string; with: string; links: string; owner: string }> = {
  institution: {
    one: 'Удалить учреждение?',
    many: 'Удалить учреждения: ',
    with: 'Вместе с учреждением удалятся его съёмки и группы, назначения сотрудников, ',
    links: many,
    owner: 'учреждению'
  },
  shoot: { one: 'Удалить съёмку?', many: 'Удалить съёмки: ', with: 'Вместе со съёмкой удалятся её группы, ', links: many, owner: 'съёмке' },
  group: {
    one: 'Удалить группу?',
    many: 'Удалить группы: ',
    with: 'Вместе с группой удалятся ',
    links: 'ссылка для родителей и её история. Переданная родителям ссылка перестанет открываться',
    owner: 'группе'
  }
};
const text = computed(() => (props.kind ? words[props.kind] : null));
</script>
<template>
  <UiRemoveDialog
    :open="!!kind"
    :title="text ? (names.length === 1 ? text.one : text.many + names.length + '?') : ''"
    :names="names"
    :busy="busy"
    testid="structure-remove-dialog"
    @confirm="$emit('confirm')"
    @close="$emit('close')"
  >
    {{ text?.with }}загруженные кадры, коды детей, условия продажи, {{ text?.links }}, файлы кадров будут стёрты с диска. Отменить удаление
    нельзя.
    <template #warning>
      <v-alert type="warning" variant="tonal" density="compact">
        Если по {{ text?.owner }} уже есть заказы, сервер откажет: такие данные сохраняются для истории заказов и оплат.
      </v-alert>
    </template>
  </UiRemoveDialog>
</template>
