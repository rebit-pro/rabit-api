<script setup lang="ts">
import type { CaseCommand, CaseErrors } from '../types';
defineProps<{ command: CaseCommand; errors: CaseErrors }>();
defineEmits<{ change: [value: Partial<CaseCommand>] }>();
</script>
<template>
  <v-select
    :model-value="command.status"
    label="Состояние обращения"
    aria-label="Состояние обращения"
    :items="[
      { title: 'В работе', value: 'in-progress' },
      { title: 'Решено', value: 'resolved' }
    ]"
    :error-messages="errors.status"
    :aria-invalid="!!errors.status"
    @update:model-value="$emit('change', { status: $event })"
  /><v-textarea
    :model-value="command.comment"
    label="Ответ родителю"
    rows="5"
    counter="2000"
    :error-messages="errors.comment"
    :aria-invalid="!!errors.comment"
    @update:model-value="$emit('change', { comment: $event })"
  />
  <p class="mf-muted">Текст и состояние появятся в истории обращения родителя. Настоящее сообщение не отправляется.</p>
</template>
