<script setup lang="ts">
import { shallowRef } from 'vue';
import PhotoImage from '../../photos/components/PhotoImage.vue';
import PhotoZoomDialog from '../../gallery/components/PhotoZoomDialog.vue';
import type { CartQuote } from '../../commerce/types';
import type { GalleryPhoto } from '../../gallery/types';
import { money } from '../../commerce/money';
defineProps<{ quote: CartQuote }>();
const enlarged = shallowRef<GalleryPhoto | null>(null);
</script>
<template>
  <section class="mf-panel order-composition">
    <h2>Состав заказа</h2>
    <ul>
      <li v-for="line in quote.lines" :key="line.id" data-testid="order-line">
        <button
          v-if="line.photo?.thumbSrc"
          type="button"
          class="order-composition__open"
          :aria-label="'Увеличить кадр ' + line.photo.code"
          @click="enlarged = line.photo"
        >
          <PhotoImage :src="line.photo.thumbSrc" :alt="'Кадр ' + line.photo.code" loading="lazy" />
        </button>
        <v-icon v-else :icon="line.photo ? 'mdi-image-outline' : 'mdi-image-multiple-outline'" size="36" class="order-composition__icon" />
        <div class="order-composition__description">
          <strong>{{ line.product.name }}</strong>
          <p>
            Серия {{ line.childCode }}<template v-if="line.photo"> · {{ line.photo.code }}</template>
          </p>
          <p>
            {{ line.quantity }}
            {{ line.product.unit ?? (line.product.printCount > 1 ? 'компл.' : 'шт.')
            }}<template v-if="line.product.printCount > 1">
              ·
              {{ line.quantity * line.product.printCount }} отпечатка</template
            >
          </p>
          <p v-if="line.discount" class="order-composition__benefit">Льгота −{{ money(line.discount) }}</p>
          <p v-if="line.coveredByGift" class="order-composition__benefit">Входит в подарок</p>
        </div>
        <strong class="order-composition__price">{{ money(line.total) }}</strong>
      </li>
    </ul>
    <PhotoZoomDialog
      :open="enlarged !== null"
      :src="enlarged?.previewSrc ?? ''"
      :label="'Кадр ' + (enlarged?.code ?? '')"
      @close="enlarged = null"
    />
    <p v-for="code in quote.gifts" :key="code" class="order-composition__gift">
      <v-icon icon="mdi-gift-outline" size="20" /> Электронный комплект серии {{ code }} в подарок
    </p>
    <p v-if="quote.invalid.length" role="alert" class="mt-4">Часть позиций недоступна. Вернитесь в корзину и уточните выбор.</p>
  </section>
</template>
<style scoped>
.order-composition h2 {
  font-size: 20px;
  margin-bottom: 12px;
}
.order-composition ul {
  list-style: none;
  padding: 0;
}
.order-composition li {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 0;
  border-bottom: 1px solid var(--mf-color-border);
}
.order-composition li:last-child {
  border-bottom: 0;
}
.order-composition__open {
  flex: 0 0 56px;
  width: 56px;
  height: 76px;
  padding: 0;
  border-radius: 5px;
  cursor: zoom-in;
}
.order-composition__open img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.order-composition__open:focus-visible {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
.order-composition__icon {
  flex: 0 0 56px;
}
.order-composition__description {
  flex: 1;
  min-width: 0;
  font-size: 14px;
  line-height: 1.6;
}
.order-composition__description p {
  color: var(--mf-color-text-secondary);
  font-size: 12px;
}
.order-composition__price {
  font-size: 14px;
  white-space: nowrap;
}
.order-composition__description .order-composition__benefit,
.order-composition__gift {
  color: var(--mf-color-link);
}
.order-composition__gift {
  font-size: 13px;
  line-height: 1.6;
  background: var(--mf-color-selected);
  border-radius: 6px;
  padding: 12px;
  margin-top: 12px;
}
@media (max-width: 440px) {
  .order-composition li {
    gap: 10px;
    flex-wrap: wrap;
  }
  .order-composition__price {
    margin-left: auto;
  }
}
</style>
