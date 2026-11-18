<script setup lang="ts">
import { computed, ref, shallowRef } from 'vue';
import { isAxiosError } from 'axios';
import { linkError, linksApi } from '../links-api';
import type { LinkGroup } from '../types';
import { shootDelivery } from '../shoot-delivery';
import { formatMoment } from '../display';

const props = defineProps<{
  groups: LinkGroup[];
  shootId: string;
  name: string;
  mayIssue: boolean;
}>();
const emit = defineEmits<{ close: []; changed: [] }>();
const rows = ref(
  props.groups
    .filter((g) => g.shootId === props.shootId && g.kind === 'regular')
    .map((group) => ({
      group,
      ...shootDelivery(group),
      key: crypto.randomUUID(),
      done: false,
      error: '',
      stale: false
    }))
);
const confirmed = shallowRef(false);
const working = shallowRef(false);
const problem = shallowRef('');
const needsReload = computed(() => rows.value.some((r) => r.stale));
const remaining = computed(() => rows.value.filter((r) => r.manual !== null && !r.done && !r.stale));
let sentAt = '';
async function deliver() {
  if (!confirmed.value || working.value || !remaining.value.length) return;
  working.value = true;
  problem.value = '';
  try {
    await linksApi.shootLink(props.shootId, props.mayIssue);
    if (!sentAt) sentAt = (await linksApi.detail(remaining.value[0]!.group.id)).referenceNow;
    for (const row of remaining.value) {
      try {
        const g = row.group;
        await linksApi.delivery(
          {
            groupId: g.id,
            revision: g.revision,
            signature: g.signature,
            requestId: row.key,
            confirmed: true
          },
          row.manual!,
          sentAt
        );
        row.done = true;
        row.error = '';
      } catch (cause) {
        row.error = linkError(cause);
        row.stale = isAxiosError(cause) && cause.response?.status === 409;
      }
    }
    emit('changed');
  } catch (cause) {
    problem.value = linkError(cause);
  } finally {
    working.value = false;
  }
}
</script>
<template>
  <v-dialog :model-value="true" max-width="660" persistent scrollable aria-labelledby="shoot-delivery-title">
    <v-card class="pa-5">
      <h2 id="shoot-delivery-title">Передача общей ссылки</h2>
      <p>{{ name }}</p>
      <v-card-text class="px-0">
        <p>
          Готовые группы откроются с момента передачи. Для групп с расписанием зафиксируется отправка, открытие произойдёт в назначенное
          время.
        </p>
        <ul class="delivery-groups">
          <li v-for="row in rows" :key="row.group.id">
            <strong>{{ row.group.name }}</strong>
            <span
              >{{ row.done ? 'Передача отмечена' : row.label
              }}<template v-if="row.group.scheduledOpenAt"> · {{ formatMoment(row.group.scheduledOpenAt) }}</template></span
            >
            <span v-if="row.error" role="alert">{{ row.error }}</span>
          </li>
        </ul>
        <p v-if="needsReload" role="alert">Состояние групп изменилось. Закройте окно и заново проверьте состав передачи.</p>
        <p v-if="problem" role="alert">{{ problem }}</p>
        <v-checkbox
          v-if="remaining.length"
          v-model="confirmed"
          label="Я передал(а) общую ссылку родителям"
          :disabled="working"
          hide-details
        />
      </v-card-text>
      <v-card-actions class="flex-wrap"
        ><v-btn :disabled="working" @click="$emit('close')">Закрыть</v-btn>
        <v-btn v-if="remaining.length" color="primary" variant="flat" :disabled="!confirmed" :loading="working" @click="deliver">
          {{ rows.some((r) => r.error) ? 'Повторить передачу' : 'Отметить передачу' }}
          ({{ remaining.length }})
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.delivery-groups {
  display: grid;
  gap: 12px;
  padding: 16px 0;
  list-style: none;
}
.delivery-groups li {
  display: grid;
}
</style>
