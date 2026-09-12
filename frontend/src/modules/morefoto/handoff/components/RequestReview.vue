<script setup lang="ts">
import type { RequestPreview, LinkGroup, StaffCommand, HandoffErrors } from '../types';
import PhotoImage from '../../photos/components/PhotoImage.vue';
defineProps<{ preview: RequestPreview; groups: LinkGroup[]; command: StaffCommand; errors: HandoffErrors }>();
const emit = defineEmits<{ change: [value: Partial<StaffCommand>] }>();
</script>
<template>
  <p class="mb-5">
    Назначение: <strong>{{ groups.find((g) => g.id === preview.targetGroupId)?.name }}</strong
    >. Переносятся все кадры каждого ребёнка; исходная группа сохраняется для выдачи заказов.
  </p>
  <section v-for="bundle in preview.bundles" :key="bundle.row.id" class="handoff-row">
    <h3>{{ groups.find((g) => g.id === bundle.row.groupId)?.name }} · {{ bundle.row.childCode }}</h3>
    <p>
      {{ bundle.photos.length }} фото · новый код ребёнка: <strong>{{ bundle.targetCode }}</strong>
    </p>
    <details open>
      <summary>Весь набор: {{ bundle.photos.length }} фото</summary>
      <div class="handoff-previews">
        <figure v-for="photo in bundle.photos" :key="photo.id">
          <PhotoImage :src="photo.previewSrc" :alt="'Снимок ' + photo.code" />
          <figcaption>{{ photo.code }}</figcaption>
        </figure>
      </div>
    </details>
  </section>
  <v-alert type="info" variant="tonal" class="mb-5"
    >После переноса набор исчезнет из исходной галереи и появится в папке сотрудников. Неоплаченные корзины потребуют проверки. Состав и
    стоимость оплаченных заказов сохранятся.</v-alert
  >
  <v-checkbox
    :model-value="command.confirmed"
    label="Проверены все кадры, подтверждаю перенос наборов"
    :error-messages="errors.confirmed"
    :aria-invalid="!!errors.confirmed"
    @update:model-value="emit('change', { confirmed: !!$event })"
  />
</template>
