<script setup lang="ts">
import { shallowRef } from 'vue';
import type { RequestPreview, ReviewPhoto, LinkGroup, StaffCommand, HandoffErrors } from '../types';
import PhotoImage from '../../photos/components/PhotoImage.vue';
import PhotoZoomDialog from '../../gallery/components/PhotoZoomDialog.vue';
defineProps<{
  preview: RequestPreview<ReviewPhoto>;
  groups: LinkGroup[];
  command: StaffCommand;
  errors: HandoffErrors;
}>();
const emit = defineEmits<{ change: [value: Partial<StaffCommand>] }>();
const enlarged = shallowRef<ReviewPhoto | null>(null);
</script>
<template>
  <p class="mb-5">
    Назначение:
    <strong>{{ preview.targetGroupName ?? groups.find((g) => g.id === preview.targetGroupId)?.name }}</strong
    >. Переносятся все кадры каждого ребёнка; исходная группа сохраняется для выдачи заказов.
  </p>
  <v-alert v-if="preview.hasOrders" type="warning" variant="tonal" class="mb-5" data-testid="transfer-orders"
    >У детей из списка уже есть заказы в исходной группе. Состав, стоимость и оплата этих заказов не изменятся; новые покупки будут в папке
    сотрудников со льготой.</v-alert
  >
  <section v-for="bundle in preview.bundles" :key="bundle.row.id" class="handoff-row" data-testid="transfer-bundle">
    <h3>
      {{ groups.find((g) => g.id === bundle.row.groupId)?.name }} ·
      {{ bundle.row.childCode }}
    </h3>
    <p>
      {{ bundle.photos.length }} фото · новый код ребёнка: <strong>{{ bundle.targetCode }}</strong
      ><template v-if="bundle.hasOrders"> · есть заказы, они сохранятся</template>
    </p>
    <details open>
      <summary>Весь набор: {{ bundle.photos.length }} фото</summary>
      <div class="handoff-previews">
        <figure v-for="photo in bundle.photos" :key="photo.id">
          <button
            v-if="photo.previewSrc"
            type="button"
            class="handoff-photo-open"
            :aria-label="'Увеличить снимок ' + photo.code"
            @click="enlarged = photo"
          >
            <PhotoImage :src="photo.previewSrc" :alt="'Снимок ' + photo.code" />
          </button>
          <figcaption>{{ photo.code }}</figcaption>
        </figure>
      </div>
    </details>
  </section>
  <PhotoZoomDialog
    :open="enlarged !== null"
    :src="enlarged?.previewSrc ?? ''"
    :label="'Снимок ' + (enlarged?.code ?? '')"
    @close="enlarged = null"
  />
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
<style scoped>
.handoff-photo-open {
  width: 100%;
  padding: 0;
  cursor: zoom-in;
}
.handoff-photo-open:focus-visible {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
</style>
